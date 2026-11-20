# 1. Project Title and Overview

**Presenter:** Member 1 · **Time:** 3 minutes max

---

## Project title

# PartsBin: A Web-Based Hardware Inventory Management System for Small PC Shops

*(Short name on screen: **PartsBin**)*

---

## Script (read it a few times, then say it in your own words)

> Good morning/afternoon. We are **[group name]**, and our project is **PartsBin**, a web-based hardware inventory management system for small PC repair and custom-build shops.

**What it is**

> PartsBin is a web application built with **PHP** and **MySQL** that runs on **XAMPP**. It keeps track of every computer part a shop sells, such as processors, graphics cards, memory, storage, power supplies, monitors and peripherals. It records how many of each are on the shelf, what they cost, and what they sell for.

**The problem it solves**

> Small PC shops often track stock in notebooks or spreadsheets. That causes three problems:
> 1. **They don't know when stock is running low** until a customer asks for a part that's gone.
> 2. **There's no history.** Nobody knows who restocked what, from which supplier, or why a count changed.
> 3. **They don't know what their inventory is worth.** Prices change with every delivery, so the real cost of the stock on the shelf is unclear.
>
> On top of that, one PC part usually comes in many versions. For example, one RAM kit comes in 8 GB, 16 GB and 32 GB, and each version has its own price and stock. Spreadsheets handle that badly.

**Our solution**

> PartsBin solves this with:
> - **Products with variants.** One product holds all its versions, and each version has its own quantity, cost and price.
> - **Automatic low-stock alerts** based on a threshold you set for each variant.
> - **A full stock history.** Every restock and adjustment is logged with the user, date, supplier and reason.
> - **Weighted-average costing.** When new stock arrives at a different price, the system recalculates the average cost automatically.
> - **Inventory valuation** by category, at cost and at retail, with potential profit.
> - **User accounts** with secure, hashed passwords and profile pictures.
> - **An AI assistant**, powered by Google Gemini, that answers questions about the current stock.

**Who it's for**

> Shop owners and staff of small computer shops who need a simple, affordable way to manage stock without buying expensive software.

**Hand-off line**

> Let's show you how it works. I'll start with the account features.

---

## Quick facts (in case the panel asks)

| | |
|---|---|
| Language | PHP 8 (no framework), HTML, CSS, JavaScript |
| Database | MySQL / MariaDB through XAMPP, using the `mysqli` extension with prepared statements |
| Server | Apache (XAMPP) |
| AI | Google Gemini, called through a small Python script (`ai/gemini_chat.py`) |
| Tables | 5: `users`, `categories`, `items`, `variants`, `stock_movements` |
| Pages | 13 PHP pages + 10 include files |
| Demo login | `admin` / `admin123` |
