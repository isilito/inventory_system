"""
gemini_chat.py - connects PartsBin to Google Gemini.

chat.php runs this script once for every question:

  python ai/gemini_chat.py

  stdin  (from PHP):  {"system": "...instructions + inventory...",
                       "messages": [{"role": "user" | "assistant", "content": "..."}]}

  stdout (to PHP):    one JSON object per line, which PHP passes straight to the browser
                      {"type": "text",  "text": "Two items are low..."}   a piece of the answer
                      {"type": "done",  "stop": "done"}                   finished (done / max_tokens / blocked)
                      {"type": "error", "message": "..."}                 something went wrong

Needs:  pip install google-genai python-dotenv
Settings (API key, model) come from the .env file in the project folder.

Try it by hand from the project folder:
  echo {"system": "Be brief.", "messages": [{"role": "user", "content": "Hi!"}]} | python ai/gemini_chat.py
"""

import json
import os
import sys
from pathlib import Path

from dotenv import load_dotenv
from google import genai
from google.genai import errors, types

# The project folder is one level up from this file (ai/ -> project)
PROJECT_DIR = Path(__file__).resolve().parent.parent
load_dotenv(PROJECT_DIR / ".env")   # puts GEMINI_API_KEY etc. into os.environ


def send(event: dict) -> None:
    """Write one JSON line and flush it, so PHP receives it right away."""
    sys.stdout.write(json.dumps(event, ensure_ascii=False) + "\n")
    sys.stdout.flush()


def to_gemini(messages: list) -> list:
    """Gemini calls the assistant "model", and each message holds a list of "parts"."""
    return [
        types.Content(
            role="model" if m["role"] == "assistant" else "user",
            parts=[types.Part(text=m["content"])],
        )
        for m in messages
    ]


def main() -> None:
    # Windows uses an old text encoding by default; ₱ and other symbols need UTF-8
    sys.stdout.reconfigure(encoding="utf-8")

    api_key = os.getenv("GEMINI_API_KEY", "").strip()
    model = os.getenv("GEMINI_MODEL", "gemini-4-hydrogen").strip()
    thinking = os.getenv("GEMINI_THINKING", "LOW").strip().upper()

    if not api_key:
        send({"type": "error", "message": "No API key. Put GEMINI_API_KEY in the .env file."})
        return

    try:
        request = json.loads(sys.stdin.buffer.read().decode("utf-8"))
        system = request["system"]
        messages = request["messages"]
    except (ValueError, KeyError) as ex:
        send({"type": "error", "message": f"Bad input from PHP: {ex}"})
        return

    client = genai.Client(
        api_key=api_key,
        http_options=types.HttpOptions(
            timeout=60_000,     # give up after 60 seconds (milliseconds)
            # Free-tier models are sometimes busy (503). Try up to 3 times,
            # waiting a little longer each time, before showing an error.
            retry_options=types.HttpRetryOptions(attempts=3, initial_delay=1, max_delay=8,
                                                 http_status_codes=[429, 500, 503]),
        ),
    )
    config = types.GenerateContentConfig(
        system_instruction=system,
        max_output_tokens=8192,
        thinking_config=types.ThinkingConfig(thinking_level=thinking),
    )

    stop = None
    try:
        # generate_content_stream gives us the answer in small chunks as it's written
        for chunk in client.models.generate_content_stream(
            model=model, contents=to_gemini(messages), config=config
        ):
            # The question itself was blocked by Google's safety filters
            if chunk.prompt_feedback and chunk.prompt_feedback.block_reason:
                stop = "blocked"
                break

            if not chunk.candidates:
                continue
            candidate = chunk.candidates[0]

            parts = candidate.content.parts if candidate.content and candidate.content.parts else []
            for part in parts:
                # Skip the model's private "thinking" parts; pass on the answer text
                if part.text and not part.thought:
                    send({"type": "text", "text": part.text})

            # finish_reason appears on the last chunk: STOP = finished normally
            if candidate.finish_reason == types.FinishReason.STOP:
                stop = "done"
            elif candidate.finish_reason == types.FinishReason.MAX_TOKENS:
                stop = "max_tokens"
            elif candidate.finish_reason:
                stop = "blocked"    # SAFETY, PROHIBITED_CONTENT, RECITATION, ...

    except errors.ClientError as ex:      # 4xx: a problem with our request
        if ex.code == 429:
            message = "Free-tier limit reached. Wait a minute and try again."
        elif ex.code in (400, 401, 403) and "key" in (ex.message or "").lower():
            message = "Gemini rejected the API key. Check GEMINI_API_KEY in .env."
        elif ex.code == 404:
            message = f"Model '{model}' was not found. Change GEMINI_MODEL in .env."
        else:
            message = f"Gemini returned an error ({ex.code}): {ex.message}"
        send({"type": "error", "message": message})
        return
    except errors.ServerError as ex:      # 5xx: a problem on Google's side
        send({"type": "error", "message": f"Gemini is having trouble ({ex.code}). Try again shortly."})
        return
    except Exception as ex:               # no internet, timeout, ...
        send({"type": "error", "message": f"Could not reach Gemini: {ex}"})
        return

    if stop is None:
        send({"type": "error", "message": "The answer was cut off. Please try again."})
    else:
        send({"type": "done", "stop": stop})


if __name__ == "__main__":
    main()
