# TestSprite Role Matrix Test Plan

Tujuan dokumen ini adalah menjadi acuan TestSprite run berikutnya agar coverage multi-akun lebih lengkap dan tidak gagal karena data kosong.

## Target URL

```text
http://localhost/itipm_php_mysql_starter/login.php
```

## Test Accounts

Semua akun memakai password:

```text
password
```

| Role | Email | Fokus Test |
|---|---|---|
| SUPERADMIN | superadmin@iti.ac.id | Full access, user management, archive, semua project/task/notification/activity |
| ADMIN | admin@iti.ac.id | Create MODERATOR/USER, project/task management untuk area yang diizinkan |
| MODERATOR | moderator@iti.ac.id | Create USER, project/task management terbatas, tidak assign ADMIN/SUPERADMIN |
| USER | user@iti.ac.id | Tidak bisa create user/project/task, hanya akses task yang ditugaskan, submit proof |

## Required Seed Data

Pastikan data berikut ada sebelum run agar test tidak false fail:

- Minimal 1 project aktif milik SUPERADMIN atau ADMIN.
- Minimal 1 project aktif milik MODERATOR.
- Minimal 1 task open yang assigned ke USER.
- Minimal 1 task submitted yang bisa direview ADMIN/MODERATOR.
- Minimal 1 task approved yang bisa direopen reviewer.
- Minimal 1 unread notification untuk setiap akun.
- Minimal 1 read notification untuk setiap akun.
- Minimal 1 notification yang punya `project_id`.
- Minimal 1 notification yang punya `task_id`.
- Minimal 1 deadline task yang overdue, due today, atau due within 3 days.

## TestSprite Additional Instruction

Gunakan instruksi ini saat menjalankan TestSprite:

```text
Run a role-matrix frontend E2E test from http://localhost/itipm_php_mysql_starter/login.php.

Use these accounts:
- SUPERADMIN: superadmin@iti.ac.id / password
- ADMIN: admin@iti.ac.id / password
- MODERATOR: moderator@iti.ac.id / password
- USER: user@iti.ac.id / password

Validate role-specific behavior:
- SUPERADMIN can access dashboard, users, projects, tasks, deadlines, notifications, activity, profile.
- ADMIN can create MODERATOR/USER but cannot create SUPERADMIN.
- MODERATOR can create USER but cannot create ADMIN/SUPERADMIN.
- USER cannot access Users page and cannot create project/task.
- USER can open assigned task and submit proof.
- Reviewer roles can review submitted proof.
- Reviewer roles can reopen approved tasks with a required reason.
- Notification inbox must support unread/read filters, mark read, delete, and open linked task/project targets.
- Deadline reminder must open the modal, submit Kirim Reminder, redirect to unread notifications, and show a reminder notification for the triggering user.

Avoid false failures:
- If a scenario requires data, first create the required project/task/notification through the UI or use existing seeded data visible in the UI.
- Do not assume notification tests can pass with an empty inbox.
- Assert visible labels, status messages, notification counts, and created record names, not only that the URL exists.
```

## Requirement Groups

### Auth

- Valid login for each role.
- Invalid login error.
- Lockout after repeated invalid login attempts.
- Logout returns to login page.

### Role Access

- SUPERADMIN sees all menus.
- ADMIN sees allowed management menus.
- MODERATOR sees limited management menus.
- USER does not see or access restricted management pages.

### User Management

- SUPERADMIN creates ADMIN/MODERATOR/USER.
- ADMIN creates MODERATOR/USER only.
- MODERATOR creates USER only.
- USER cannot access user management.
- Edit user and reset password only for permitted target roles.

### Project Management

- Authorized roles create project with at least one member.
- Unauthorized USER cannot create project.
- Project detail opens and shows owner, members, task count, deadline, status, progress.
- Authorized roles edit project and manage members.
- Archive follows permission and progress rules.

### Task Workflow

- Authorized roles create task with project, deadline, and assignee.
- USER opens assigned task only.
- USER submits proof with note and optional file.
- Reviewer approves/rejects submitted proof.
- Reject requires a reason.
- Approved task can be reopened with reason.
- Submission and review history remains visible.

### Notifications

- Inbox opens from sidebar.
- Unread/read/all filters work.
- Mark read moves notification to read state.
- Delete removes notification after confirmation.
- Linked notification opens project/task target.
- Deadline reminder creates a visible unread notification for triggering user.

### Activity Log

- Each role sees allowed activity records.
- Filters by action, actor, and target work.
- Linked activity targets open the expected page.

## Acceptance Criteria

- No test should fail only because a role has no seed data.
- Role restrictions must be verified both by menu visibility and direct URL access.
- Created records should be verified by their unique generated names.
- Notification tests must start with or create at least one notification for the active user.
- Final report must group test cases by requirement and list failed tests with exact reproduction notes.
