# TestSprite Master Prompt

Gunakan prompt ini di TestSprite / AI web testing agent.

---

You are testing a PHP + MySQL web application named **ITI PROJECT MANAGER**.

The app is a Trello-like project/task management system for Institut Teknologi Indonesia internal workflow.

Base URL:

```text
{{APP_BASE_URL}}/login.php
```

Main roles:

1. SUPERADMIN
2. ADMIN
3. MODERATOR
4. USER

Test accounts:

```text
SUPERADMIN: superadmin@iti.ac.id / password
ADMIN: admin@iti.ac.id / password
MODERATOR: moderator@iti.ac.id / password
USER: user@iti.ac.id / password
```

## Core Rules to Validate

### Role Access

- SUPERADMIN can see and manage all data.
- ADMIN can manage Moderator/User and projects/tasks under allowed hierarchy.
- MODERATOR can manage User and projects/tasks under allowed hierarchy.
- USER can only view assigned project/task and submit proof.
- USER must not be able to create project, create task, manage users, or review task.

### Project Rules

- Project cannot be created without at least one member besides owner.
- A member added to a project must be able to see that project after login.
- Project detail should show owner, members, tasks, deadline, progress, status.
- Owner/manager/SUPERADMIN can manage project members.
- Member with active task cannot be removed from project.
- Non-SUPERADMIN cannot archive project unless progress is 100%.

### Task Rules

- Task cannot be created without receiver/assignee.
- Task receiver must be a project member.
- Task deadline cannot exceed project deadline.
- User assigned to task can submit proof.
- User cannot submit twice while previous submit is still waiting for review.
- Reviewer can approve/reject.
- Reject requires note/reason.
- After reject, user can submit again.
- Approved task can be reopened by reviewer with reason.

### Notification Rules

- Unread badge should appear in sidebar.
- Notification list supports unread/read filters.
- Notification type filter should work.
- Clicking notification should open related task/project and mark it read.

### Activity Log Rules

- Activity log must follow hierarchy:
  - SUPERADMIN can see all.
  - ADMIN cannot see SUPERADMIN activity.
  - MODERATOR cannot see ADMIN/SUPERADMIN activity.
  - USER should only see relevant/lower permitted logs.
- Activity log rows related to task/project should be clickable.

### Dashboard Rules

- KPI values should be real data from MySQL.
- Dashboard should respect active role visibility.
- Period filters 1/3/6/12 months should work.
- Deadline & Tindak Lanjut items should open task detail.

### Upload Rules

- Valid file upload like JPG/PNG/PDF should work.
- PHP/script file upload should be rejected.
- File over 10MB should be rejected.
- Uploaded file links should open safely.

### Responsive Rules

- App should be usable on desktop and mobile.
- Sidebar becomes horizontal mobile navigation.
- Task/User/Activity tables should be readable or horizontally scrollable.

## Testing Instruction

Perform exploratory and end-to-end tests for each role.

For every bug found, report:
1. Role used
2. Page/menu
3. Steps to reproduce
4. Expected result
5. Actual result
6. Severity: Critical / High / Medium / Low
7. Screenshot or visual clue if available

Do not only check whether pages load. Check role restrictions and workflow correctness.
