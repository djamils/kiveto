---
name: No auto-commit on fixes
description: Never commit automatically when fixing bugs or UI issues — always wait for explicit user approval
type: feedback
---

Never commit changes unless the user explicitly says "commit" or asks to create a commit.

**Why:** User discovered I committed fix commits without validation. Code must be tested in the browser before committing — especially UI changes.

**How to apply:** After any fix (bug, UI, routing, etc.), stop and say "check X in the browser, let me know when it's good and I'll commit." Do not commit proactively, not even "obvious" one-liner fixes.
