# PartsBin — Final Project Presentation Kit

Everything the group needs for the Final Project Presentation, mapped to the official guidelines.

| # | File | Covers guideline |
|---|---|---|
| 1 | [01_Title_and_Overview.md](01_Title_and_Overview.md) | **Presentation Flow 1** — Project title and overview (script) |
| 2 | [02_Feature_Demo_Script.md](02_Feature_Demo_Script.md) | **Presentation Flow 2** — Feature presentation, split by member, with exact clicks and lines |
| 3 | [03_Code_Questioning_Reviewer.md](03_Code_Questioning_Reviewer.md) | **Presentation Flow 3** — Code questioning: PHP concepts, logic, functions, likely questions and answers |
| 4 | [04_Database_Reference.md](04_Database_Reference.md) | **Presentation Flow 3** — Database tables, relationships and every important query explained |
| 5 | [05_Backup_Flash_Drives.md](05_Backup_Flash_Drives.md) + [make_backup.ps1](make_backup.ps1) | **Required** — two flash drives with a complete copy of the source code |
| 6 | [06_Assessment_Form_Info.md](06_Assessment_Form_Info.md) | **Required** — details to copy onto the printed Project Assessment Form |

---

## 25-minute run sheet

The 25 minutes cover the **whole** process, so setup has to be fast. Rehearse with a timer at least twice.

| Time | Segment | Who | Notes |
|---|---|---|---|
| 0:00 – 1:30 | Setup: laptop on projector, XAMPP running, browser on `login.php` | Member 4 (tech lead) | Do this **before** you are called if the room allows it |
| 1:30 – 4:30 | **1. Title and overview** | Member 1 | 3 minutes max. Script in file 01 |
| 4:30 – 15:30 | **2. Feature demo** (4 parts, about 2.5 min each) | Members 1 → 2 → 3 → 4 | Script in file 02. Hand over with one sentence, no dead air |
| 15:30 – 25:00 | **3. Code questioning** | Anyone (panel picks) | Have VS Code open with the project folder. Use file 03 to study |

> Fill in the real names: **Member 1** = ________ · **Member 2** = ________ · **Member 3** = ________ · **Member 4** = ________
> With 3 members, Member 3 also takes Part D of the demo. With 5 members, the 5th takes the AI assistant and Valuation from Part D.

---

## Day-of checklist

**The night before**
- [ ] Every member has read file 03 fully and can explain `change_stock()`, `upload_image()`, `db_run()` and the login flow without notes
- [ ] Run `make_backup.ps1` to **both** flash drives (see file 05), then open each drive on another computer to check it works
- [ ] Fill out the Project Assessment Form (details in file 06) and print it
- [ ] Charge the laptop. Pack the charger and an HDMI adapter
- [ ] Reset the demo database so the numbers in the demo script match (file 02, "Before you start")

**At the venue**
- [ ] Complete uniform: all members
- [ ] All members present (attendance is checked throughout)
- [ ] Printed and filled-out Project Assessment Form in hand
- [ ] 2 flash drives in hand
- [ ] XAMPP: **Apache** and **MySQL** both green
- [ ] Browser zoom at 110–125% so the projector is readable
- [ ] Logged **out** (the demo starts at the login page)
- [ ] VS Code open on the project folder, file tree visible, font size bumped up (Ctrl + =)
- [ ] Internet works, if you'll show the AI assistant (needs Gemini). If not, skip it and say why
