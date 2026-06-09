-- ITI PROJECT MANAGER - FRESH DATABASE SCHEMA
-- Import file ini lewat phpMyAdmin.
-- Database default: itipm_db
-- Semua akun demo memakai password: password

CREATE DATABASE IF NOT EXISTS itipm_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE itipm_db;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS task_attachments;
DROP TABLE IF EXISTS project_attachments;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS task_comments;
DROP TABLE IF EXISTS task_submissions;
DROP TABLE IF EXISTS task_assignees;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS project_members;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('SUPERADMIN','ADMIN','MODERATOR','USER') NOT NULL DEFAULT 'USER',
    unit VARCHAR(120) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    owner_id INT NOT NULL,
    status ENUM('draft','active','review','completed','archived') NOT NULL DEFAULT 'active',
    deadline_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    INDEX idx_projects_owner (owner_id),
    INDEX idx_projects_status (status),
    INDEX idx_projects_deadline (deadline_at),
    CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    role_in_project ENUM('owner','manager','member') NOT NULL DEFAULT 'member',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_project_user (project_id, user_id),
    INDEX idx_project_members_project (project_id),
    INDEX idx_project_members_user (user_id),
    CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_pm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    created_by INT NOT NULL,
    status ENUM('open','submitted','approved','rejected') NOT NULL DEFAULT 'open',
    deadline_at DATETIME NOT NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    INDEX idx_tasks_project (project_id),
    INDEX idx_tasks_status (status),
    INDEX idx_tasks_deadline (deadline_at),
    INDEX idx_tasks_created_by (created_by),
    CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_tasks_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_assignees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_task_user (task_id, user_id),
    INDEX idx_task_assignees_task (task_id),
    INDEX idx_task_assignees_user (user_id),
    CONSTRAINT fk_ta_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_ta_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    submitted_by INT NOT NULL,
    note TEXT NULL,
    file_path VARCHAR(255) NULL,
    status ENUM('submitted','approved','rejected') NOT NULL DEFAULT 'submitted',
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    review_note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_submissions_task (task_id),
    INDEX idx_submissions_submitted_by (submitted_by),
    INDEX idx_submissions_status (status),
    CONSTRAINT fk_sub_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_user FOREIGN KEY (submitted_by) REFERENCES users(id),
    CONSTRAINT fk_sub_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_comments_task (task_id),
    CONSTRAINT fk_comments_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NULL,
    project_id INT NULL,
    task_id INT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_read (is_read),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_notifications_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(180) NOT NULL,
    detail TEXT NULL,
    project_id INT NULL,
    task_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_user (user_id),
    INDEX idx_activity_created (created_at),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_activity_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    CONSTRAINT fk_activity_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NULL,
    size_bytes INT NULL,
    path VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project_attachments_project (project_id),
    CONSTRAINT fk_project_attach_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_project_attach_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NULL,
    size_bytes INT NULL,
    path VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_task_attachments_task (task_id),
    CONSTRAINT fk_task_attach_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_task_attach_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password semua akun demo: password
-- Hash ini kompatibel dengan password_verify('password', hash)
INSERT INTO users (id, name, email, password_hash, role, unit, status, created_by, created_at) VALUES
(1, 'Super Admin', 'superadmin@iti.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCIsqYty4v1FB9XyfhqW', 'SUPERADMIN', 'Team PDSI', 'active', NULL, NOW()),
(2, 'Admin Rektor', 'admin@iti.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCIsqYty4v1FB9XyfhqW', 'ADMIN', 'Rektor / Warek A', 'active', 1, NOW()),
(3, 'Kepala PMB', 'moderator@iti.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCIsqYty4v1FB9XyfhqW', 'MODERATOR', 'Kepala PMB', 'active', 2, NOW()),
(4, 'Staf PMB', 'user@iti.ac.id', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llCIsqYty4v1FB9XyfhqW', 'USER', 'Staf PMB', 'active', 3, NOW());

INSERT INTO projects (id, title, description, owner_id, status, deadline_at, created_at) VALUES
(1, 'Akreditasi Program Studi 2026', 'Koordinasi dokumen dan persiapan akreditasi.', 2, 'active', DATE_ADD(NOW(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 4 MONTH)),
(2, 'Penerimaan Mahasiswa Baru', 'Persiapan konten, data pendaftar, dan koordinasi PMB.', 3, 'active', DATE_ADD(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 2 MONTH)),
(3, 'Digitalisasi Arsip Akademik', 'Migrasi dan validasi dokumen akademik.', 2, 'review', DATE_ADD(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 1 MONTH));

INSERT INTO project_members (project_id, user_id, role_in_project, created_at) VALUES
(1, 1, 'manager', NOW()),
(1, 2, 'owner', NOW()),
(1, 3, 'manager', NOW()),
(1, 4, 'member', NOW()),
(2, 1, 'manager', NOW()),
(2, 3, 'owner', NOW()),
(2, 4, 'member', NOW()),
(3, 1, 'manager', NOW()),
(3, 2, 'owner', NOW()),
(3, 4, 'member', NOW());

INSERT INTO tasks (id, project_id, title, description, created_by, status, deadline_at, reviewed_by, reviewed_at, created_at) VALUES
(1, 1, 'Kumpulkan dokumen kurikulum', 'Upload bukti dokumen kurikulum terbaru.', 2, 'open', DATE_ADD(NOW(), INTERVAL 7 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(2, 1, 'Validasi data dosen', 'Cek kelengkapan data dosen.', 2, 'submitted', DATE_ADD(NOW(), INTERVAL 4 DAY), NULL, NULL, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(3, 2, 'Update brosur PMB', 'Revisi brosur digital PMB.', 3, 'approved', DATE_SUB(NOW(), INTERVAL 2 DAY), 3, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),
(4, 3, 'Scan arsip akademik tahap 1', 'Upload hasil scan tahap pertama.', 2, 'rejected', DATE_ADD(NOW(), INTERVAL 3 DAY), 2, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY));

INSERT INTO task_assignees (task_id, user_id, created_at) VALUES
(1, 4, NOW()),
(2, 4, NOW()),
(3, 4, NOW()),
(4, 4, NOW());

INSERT INTO task_submissions (task_id, submitted_by, note, file_path, status, reviewed_by, reviewed_at, review_note, created_at) VALUES
(2, 4, 'Data dosen sudah dicek dan diunggah.', NULL, 'submitted', NULL, NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 4, 'Brosur PMB final sudah diunggah.', NULL, 'approved', 3, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Sudah sesuai.', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(4, 4, 'Scan tahap 1 selesai.', NULL, 'rejected', 2, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Beberapa file belum terbaca jelas.', DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO notifications (user_id, title, message, project_id, task_id, is_read, created_at) VALUES
(2, 'Bukti task dikirim', 'Staf PMB mengirim bukti validasi data dosen.', 1, 2, 0, NOW()),
(4, 'Task ditolak', 'Scan arsip akademik perlu revisi.', 3, 4, 0, NOW());

INSERT INTO activity_logs (user_id, action, detail, project_id, task_id, created_at) VALUES
(2, 'Project dibuat', 'Akreditasi Program Studi 2026', 1, NULL, DATE_SUB(NOW(), INTERVAL 4 MONTH)),
(3, 'Project dibuat', 'Penerimaan Mahasiswa Baru', 2, NULL, DATE_SUB(NOW(), INTERVAL 2 MONTH)),
(4, 'Bukti task dikirim', 'Validasi data dosen', 1, 2, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 'Task ditolak', 'Scan arsip akademik tahap 1', 3, 4, DATE_SUB(NOW(), INTERVAL 1 DAY));

-- Cek akun demo:
-- superadmin@iti.ac.id / password
-- admin@iti.ac.id / password
-- moderator@iti.ac.id / password
-- user@iti.ac.id / password
