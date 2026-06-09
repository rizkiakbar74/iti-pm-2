# E2E Test Scenarios

## Scenario 1 — Login & Navigation

For each account:
1. Open login page.
2. Login.
3. Confirm dashboard appears.
4. Open each visible menu.
5. Confirm no PHP error/blank page.
6. Logout.

Expected:
- All demo accounts can login.
- USER should not see Pengguna menu.
- No debug text like `Hash length`.

## Scenario 2 — Project Creation Requires Member

Role: ADMIN

Steps:
1. Login as admin.
2. Open Project.
3. Try creating project without selecting member.
4. Then create project with `Staf PMB` selected.

Expected:
- Without member: rejected.
- With member: project created.
- User can see project after login.

## Scenario 3 — Task Creation Requires Receiver

Role: ADMIN

Steps:
1. Open project detail.
2. Create task without receiver.
3. Create task with `Staf PMB` receiver.

Expected:
- Without receiver: rejected.
- With receiver: task created.
- Receiver can see task.

## Scenario 4 — User Submit & Admin Review

Roles: USER then ADMIN

Steps:
1. Login as user.
2. Open assigned task.
3. Submit proof with note.
4. Try submit again before review.
5. Login as admin.
6. Open same task.
7. Reject without reason.
8. Reject with reason.
9. Login as user.
10. Submit again.
11. Login as admin.
12. Approve.

Expected:
- Duplicate pending submit rejected.
- Reject without reason rejected.
- Reject with reason works.
- User can submit again after reject.
- Approve marks task verified/approved.

## Scenario 5 — Notification Click

Steps:
1. Trigger submit/comment/deadline reminder.
2. Open notification center.
3. Filter unread.
4. Click a notification.

Expected:
- Opens related task/project.
- Notification becomes read.
- Sidebar unread count decreases.

## Scenario 6 — Activity Log Click & Hierarchy

Steps:
1. Generate activity as SUPERADMIN, ADMIN, MODERATOR, USER.
2. Login each role.
3. Open Activity Log.
4. Verify visible logs.
5. Click task/project activity.

Expected:
- Role hierarchy respected.
- Click opens correct task/project.

## Scenario 7 — Upload Security

Steps:
1. Upload valid PDF/JPG as proof.
2. Upload `.php` file.
3. Upload file over 10MB.

Expected:
- Valid file accepted.
- PHP/script rejected.
- >10MB rejected.

## Scenario 8 — Dashboard Real Data

For each role:
1. Open Dashboard.
2. Check KPI.
3. Click period 1/3/6/12.
4. Click Deadline & Tindak Lanjut item.

Expected:
- KPI and chart visible.
- Data respects role visibility.
- Deadline item opens task detail.

## Scenario 9 — Mobile Responsive

Use mobile viewport:
1. Login.
2. Check horizontal navigation.
3. Open Dashboard.
4. Open Tugas.
5. Open Pengguna if allowed.
6. Open Activity.
7. Open Detail Task/Project.

Expected:
- No unusable broken layout.
- Wide tables can scroll horizontally.
