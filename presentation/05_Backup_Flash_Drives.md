# 5. Backup: Two Flash Drives

**Requirement:** bring **two (2) flash drives**, each with a **complete copy of the system source code**, in case one fails.

## Make the backups (about 1 minute per drive)

1. Start **MySQL** in XAMPP if you also want the current data exported (optional: the app can rebuild sample data on its own).
2. Plug in flash drive #1. Note its letter in File Explorer (e.g. `E:`).
3. Open PowerShell in the project folder and run:
   ```powershell
   powershell -ExecutionPolicy Bypass -File presentation\make_backup.ps1 -Destination E:\
   ```
4. Repeat with flash drive #2 (e.g. `F:\`).

Add `-IncludeEnv` to also copy the `.env` file, which holds the Gemini API key. Without it, the AI chat won't work from the backup, but everything else will. If you include it, don't lend the drive to anyone.

## What's on each drive

```
PartsBin_Backup_2026-11-21_0930\
├── partsbin\                 ← full source code (copy into C:\xampp\htdocs\)
├── partsbin_source.zip       ← same thing as one zip
├── partsbin_database.sql     ← database export (only if MySQL was running)
└── RESTORE_INSTRUCTIONS.txt  ← how to run it on another computer
```

The script compares file counts between the project and the copy, and warns you if they don't match.

## Test each drive (don't skip this)

On **another computer** if possible, or on your own after renaming your `htdocs` folder:
- [ ] Copy `partsbin\` into `C:\xampp\htdocs\`
- [ ] Start Apache and MySQL → open `http://localhost/partsbin/` → log in with `admin` / `admin123`
- [ ] Open one product page. Pictures load.

## Label the drives

Put a strip of tape on each drive: **group name + "PartsBin backup 1 / 2"**.
Keep them with **two different members**, so one person forgetting their bag doesn't lose both.
