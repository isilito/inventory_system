# 2. Feature Presentation: Demo Script

**Total time:** about 11 minutes · **4 parts × about 2.5 min** · Everyone presents.

The guidelines say: *show the actual system processes, keep it concise, avoid lengthy explanations.*
So each step below is **click → one or two sentences → next**. The lines in quotes are suggestions; say them naturally.

---

## Before you start (do this the night before AND re-check on the day)

**Reset to fresh sample data** so the numbers below match exactly:
1. Open phpMyAdmin → select the `partsbin` database → **Operations** → **Drop the database**.
2. Delete the files inside `uploads/products/` and `uploads/avatars/` (keep the `.htaccess` file in `uploads/`).
3. Open `http://localhost/partsbin/` (or your folder name). The app rebuilds the database and sample data on its own.
4. Do **one** practice run of the whole script, then reset again so the presentation starts clean.

**Have ready on the desktop:**
- A small profile photo (JPG/PNG under 2 MB) for sign up
- A product photo, for example a RAM stick (JPG/PNG under 2 MB)

**Expected sample numbers after a fresh reset** (useful for checking the reset worked):
- *Core Ultra 5 Desktop Processor → 225 (up to 4.9GHz)*: **4 on hand**, avg. cost **₱10,100.00**
- *Core Ultra 5 → 245KF Unlocked (no iGPU)*: **0 on hand** (shows as Out of stock)

---

## Part A: Accounts and security · Member 1 · about 2.5 min

| Step | Do this | Say this |
|---|---|---|
| A1 | Start on the **Log in** page. Type `admin` and a **wrong** password → Log in | "Wrong credentials show one general message. We don't reveal whether the username or the password was wrong." |
| A2 | Click **Sign up**. Fill in your full name, username, email, password and confirm password. Choose a **profile picture** (the preview appears right away) → **Sign up** | "New accounts are validated: username format, valid email, password at least 6 characters, passwords must match, and no duplicate username or email. The password is stored as a hash, never as plain text." |
| A3 | You land on the **Dashboard**, logged in, with your picture in the greeting | "After signing up you're logged in right away. The greeting changes with the time of day." |
| A4 | Click your **username chip** (top right) → **My profile**. Change your full name → **Save profile** | "Users can update their details and profile picture. Uploads are checked for type and size." |
| A5 | *(optional if short on time)* Show the **Change password** form, without submitting | "Changing a password requires the current password first." |

**Hand-off:** "Now [Member 2] will show the dashboard and how products are organized."

---

## Part B: Dashboard, categories and product list · Member 2 · about 2.5 min

| Step | Do this | Say this |
|---|---|---|
| B1 | Click **Dashboard** | "The dashboard summarizes the whole shop: number of products and variants, units in stock, inventory value at cost, and how many variants need restocking." |
| B2 | Scroll to **Products** (picture gallery) and **Low stock** | "Every product has a picture. The Low stock list shows variants at or below their threshold, emptiest first, with a Restock button." |
| B3 | Point at **Recent activity** | "Every stock change is logged: who did it, when, and the quantity change." |
| B4 | Click **Categories**. Add a category: Name `Cooling`, Description `CPU coolers and case fans` → **Add category** | "Categories group products. Each one shows its product count, units and value. Names must be unique." |
| B5 | Click **Edit** on `Cooling`, change the description → **Save changes** | "Categories can be renamed or deleted. If one is deleted, its products become Uncategorized instead of being deleted." |
| B6 | Click **Products**. Type `ryzen` in search → **Filter**. Then choose **Stock: Out of stock** → **Filter** → **Clear** | "Products can be searched by name, brand, variant or SKU, and filtered by category, condition tag and stock level." |

**Hand-off:** "Next, [Member 3] will register a new product with several variants at once."

---

## Part C: Registering products with variants · Member 3 · about 2.5 min

| Step | Do this | Say this |
|---|---|---|
| C1 | **Products** → **+ New product** | "This is product registration. One form saves the product, its picture, and all its variants together." |
| C2 | Fill in: Name `FURY Beast DDR5 Desktop Memory`, Brand `Kingston`, Category `Memory`, Status tag `New`. Choose the **product photo** | |
| C3 | Variant rows. Row 1: `16GB (2x8GB) 5600MHz`, SKU `RAM-KIN-16`, qty `5`, cost `3200`, price `3800`, low at `2`. Row 2: `32GB (2x16GB) 6000MHz`, SKU `RAM-KIN-32`, qty `3`, cost `6100`, price `7200`, low at `2` | |
| C4 | Click **+ Add another variant** → Row 3: `64GB (2x32GB) 6000MHz`, qty `1`, cost `11800`, price `13900`, low at `1` | "You can add as many variants as you need. All of them are saved with a single database INSERT." |
| C5 | **Register product** | "The product page shows the picture, totals and each variant with its own stock status. The opening quantities were also logged in the stock history at the bottom." |
| C6 | On a variant, click **Edit** → change the sell price → **Save variant** | "Label, SKU, cost, price and threshold can be edited here. Quantity can't. Stock only changes through the Stock page, so every change is logged." |
| C7 | *(optional)* Show the **Add variants** form at the bottom, and point at the **Delete** button (don't click it) | "Deleting a product also removes its picture file, its variants and their history." |

**Hand-off:** "Finally, [Member 4] will show how stock is updated and reported."

---

## Part D: Stock, restocks, valuation and AI · Member 4 · about 3 min

| Step | Do this | Say this |
|---|---|---|
| D1 | Click **Stock** | "Every variant in one table: quantity, threshold, status, average cost and value. The tabs show counts for In stock, Low and Out." |
| D2 | Search `225` → **Filter** → on **225 (up to 4.9GHz)** click **Update** | "This one has **4 on hand** at an average cost of **₱10,100**." |
| D3 | **Restock** form: Quantity `2`, Unit cost `10400`, Supplier `PC Hub Wholesale`, Reference `INV-2026-001` → **Add to stock** | "Now it's **6 on hand**, and the average cost became **₱10,200**. That's weighted-average costing: (4 × 10,100 + 2 × 10,400) ÷ 6 = 10,200." |
| D4 | **Adjust** form: Direction `Remove (−)`, Quantity `99`, Reason `Damaged` → **Save adjustment** | "The system won't allow negative stock. It says there are only 6 on hand." |
| D5 | Same form: Quantity `1`, Reason `Damaged`, Note `Bent pins` → **Save adjustment** | "A valid adjustment is logged with its reason. It's in Recent movements right below." |
| D6 | Click **Restocks** → set the **From** date to today → **Filter** | "The restock history can be filtered by date range and supplier, and shows total units and money spent." |
| D7 | Click **Valuation** | "Inventory value at cost and at retail, broken down by category, with potential profit and margin. The Print button produces a clean printout." |
| D8 | Click the **chat button** (bottom right). Click a suggestion or type `Which parts are out of stock?` | "The AI assistant reads the live inventory from the database each time you ask, so its answers are always current. It can read data but can't change it." |
| D9 | Click **Log out** | "Logging out clears the session and returns to the login page. That concludes our demo. We're ready for questions." |

**If the AI fails** (no internet or quota): skip it smoothly. "The assistant needs an internet connection to Google Gemini. The rest of the system works fully offline."

---

## Demo tips

- **The person who talks is the person who clicks.** Swap seats or pass the mouse at hand-offs.
- Don't read code during the demo. Code comes in the questioning part.
- If something errors, stay calm: "That's the validation working," or move on. Never debug in front of the panel.
- Keep the browser window maximized. Close other tabs and notifications (Windows Focus Assist on).
