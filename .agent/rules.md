# Giga Stock Alerts — Agent Rules

## Project Context
This is a WordPress WooCommerce plugin called "Giga Stock Alerts".
It captures customer demand on out-of-stock products and notifies 
them via email when restocked. Currently building the FREE version only.

## Always Do
- Before writing any code, state which file you're editing and why
- After writing code, list what still needs to be done
- If a task touches multiple files, show the plan before starting
- Use the `wordpress-plugin-standards` skill for all PHP code

## Never Do
- Never add Pro features (SMS, WhatsApp, license checks)
- Never modify WooCommerce or WordPress core files
- Never write raw SQL without `$wpdb->prepare()`
- Never assume a task is complete without listing verification steps

## When Stuck
If you encounter a WooCommerce hook or function you're unsure about,
say so explicitly rather than guessing. Ask for clarification.
```

---

### Step 5 — Terminal Auto-Execution Policy সেট করো

Antigravity open করলে **Agent Manager configuration screen** দেখাবে। বাম দিকে development mode আছে — **Agent-assisted development** select করো (recommended)। এতে তুমি control এ থাকো কিন্তু AI safe automations এ help করে। 

---

### Step 6 — Model Select করো

**Gemini 3 Pro** select করো main model হিসেবে — এটা code reasoning, large context, এবং multi-step planning এর জন্য optimize করা।  অথবা Claude Sonnet ও available আছে।

---

## এখন First Mission দাও

Setup হয়ে গেলে Agent Manager এ গিয়ে এই mission দাও:
```
I'm building a WordPress WooCommerce plugin called "Giga Stock Alerts". 
Read the rules in .agent/rules.md and use the wordpress-plugin-standards skill.

First task: Create the complete folder scaffold for the plugin with all 
stub files as described in the rules. Start with the main plugin file 
giga-stock-alerts.php and the folder structure. Show me your plan first 
before creating any files.