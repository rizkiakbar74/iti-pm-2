# TestSprite AI Testing Report (MCP)

---

## 1️⃣ Document Metadata

- **Project Name:** itipm_php_mysql_starter
- **Test Target:** http://localhost/itipm_php_mysql_starter/login.php
- **Date:** 2026-06-08
- **Prepared by:** TestSprite AI Team + Codex
- **Environment:** Local XAMPP Apache on port 80
- **Test Type:** Frontend E2E rerun after fixes
- **Total Tests:** 30
- **Passed:** 28
- **Failed:** 2
- **Blocked:** 0
- **Pass Rate:** 93.33%

---

## 2️⃣ Requirement Validation Summary

### User Login and Session Management

- **TC001 Successful login and dashboard access:** ✅ Passed. Valid demo login reaches the dashboard.
- **TC003 Logout returns to the login page:** ✅ Passed. Logout ends the session and returns to login.
- **TC009 Invalid login shows an error:** ✅ Passed. Invalid credentials show the expected error.
- **TC018 Repeated invalid login attempts trigger lockout:** ✅ Passed. The previous lockout mismatch is fixed; lockout is now detected after repeated failed attempts.

### Dashboard and Navigation

- **TC012 Dashboard metrics and navigation are available after login:** ✅ Passed. Dashboard and navigation render after authentication.
- **TC015 Task list is accessible from the main navigation:** ✅ Passed. Task navigation works.

### Project Management

- **TC008 Create a project with members:** ✅ Passed.
- **TC013 Open project details and review project information:** ✅ Passed.
- **TC019 Edit permitted project fields:** ✅ Passed.
- **TC023 Manage project members:** ✅ Passed.
- **TC029 Archive a project when allowed:** ✅ Passed.

### Task Workflow

- **TC002 Create a task for a project:** ✅ Passed.
- **TC004 Submit proof for a task:** ✅ Passed.
- **TC006 Review submitted proof:** ✅ Passed.
- **TC007 Reopen an approved task with a reason:** ✅ Passed.
- **TC025 View task submission and review history:** ✅ Passed.

### Task Comments

- **TC027 Add a comment to a task:** ✅ Passed.
- **TC030 Delete an allowed task comment:** ✅ Passed.

### Notifications and Deadlines

- **TC010 Filter notifications by status:** ✅ Passed.
- **TC011 Mark a notification as read:** ❌ Failed. TestSprite found the current test user had `0 belum dibaca dari 0 notifikasi`, so there was no visible notification to mark read.
- **TC014 Delete a notification:** ✅ Passed. Delete flow worked in the generated scenario.
- **TC016 Open a linked notification target:** ✅ Passed. Linked notification navigation worked in the generated scenario.
- **TC017 Generate a deadline reminder:** ❌ Failed during the rerun. TestSprite clicked `Kirim Reminder` and reached notifications, but the triggering user's inbox still showed 0 notifications.
- **TC022 Notifications inbox opens from the sidebar:** ✅ Passed.

### User Management and Authorization

- **TC005 Block unauthorized access to the Users page:** ✅ Passed.
- **TC020 Create a user with an allowed role:** ✅ Passed.
- **TC024 Edit an existing user:** ✅ Passed.
- **TC028 Reset a user password:** ✅ Passed.

### Activity Log

- **TC021 View activity records for the current role:** ✅ Passed.
- **TC026 Filter activity by action actor or target:** ✅ Passed.

---

## 3️⃣ Coverage & Matching Metrics

| Requirement | Total Tests | ✅ Passed | ❌ Failed | Blocked |
|---|---:|---:|---:|---:|
| User Login and Session Management | 4 | 4 | 0 | 0 |
| Dashboard and Navigation | 2 | 2 | 0 | 0 |
| Project Management | 5 | 5 | 0 | 0 |
| Task Workflow | 5 | 5 | 0 | 0 |
| Task Comments | 2 | 2 | 0 | 0 |
| Notifications and Deadlines | 6 | 4 | 2 | 0 |
| User Management and Authorization | 4 | 4 | 0 | 0 |
| Activity Log | 2 | 2 | 0 | 0 |
| **Total** | **30** | **28** | **2** | **0** |

---

## 4️⃣ Key Gaps / Risks

- **TC017 was fixed after this rerun.** Root cause: deadline reminder notifications were sent to task assignees, while TestSprite checked the inbox of the user who triggered the reminder. `actions/deadline-reminder.php` now also creates a summary notification for the triggering user. Manual HTTP verification now shows `REMINDER_NOTIFICATION_OK` and `1 belum dibaca dari 1 notifikasi`.
- **TC011 still needs deterministic notification seed data.** The failed run started that test with zero notifications for the active user. Either seed at least one unread notification for the test login user before TC011, or make the test create a notification before marking it read.
- **Generated assertions are still broad in several passed tests.** Some generated scripts assert successful navigation rather than exact content/state. For future confidence, tighten assertions around created entity names, status labels, and notification counts.

---
