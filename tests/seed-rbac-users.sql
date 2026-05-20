-- seed-rbac-users.sql -- Test fixtures for Phase 1 RBAC smoke tests. MUST be removed before any production deploy.

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES ('RBAC Test SUPER_ADMIN', 'rbac-test-super_admin@crsholdings.ph', '$2y$12$xE/eCHvd8HD1m4DDtTl82uDZJNV0ADvzhz2V1InTGhsIxcKhtpLUu', 'SUPER_ADMIN', 1)
ON DUPLICATE KEY UPDATE role=VALUES(role), is_active=1, password_hash=VALUES(password_hash);

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES ('RBAC Test ADMIN', 'rbac-test-admin@crsholdings.ph', '$2y$12$5kmzT3TmeXzI7M3OFjB3Ne68L9I1uVpOo.yQ09kJaSBbMkClyiwd6', 'ADMIN', 1)
ON DUPLICATE KEY UPDATE role=VALUES(role), is_active=1, password_hash=VALUES(password_hash);

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES ('RBAC Test MANAGER', 'rbac-test-manager@crsholdings.ph', '$2y$12$YuD9/Avc6ivZ5Stch6qQO.7IAk5JjvmsYFi8hvlTQs5clRO7qYZk2', 'MANAGER', 1)
ON DUPLICATE KEY UPDATE role=VALUES(role), is_active=1, password_hash=VALUES(password_hash);

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES ('RBAC Test LOAN_OFFICER', 'rbac-test-loan_officer@crsholdings.ph', '$2y$12$zkRmTBheV2sNDjTj2hxeCO2uk.ymcBwTfiji81MEVZT2bGy.iRrd2', 'LOAN_OFFICER', 1)
ON DUPLICATE KEY UPDATE role=VALUES(role), is_active=1, password_hash=VALUES(password_hash);

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES ('RBAC Test STAFF', 'rbac-test-staff@crsholdings.ph', '$2y$12$9RSrtAoEdSeuH2QDNl7jgunriUnS4bA55P2d0vq17CGBSiG3sS06K', 'STAFF', 1)
ON DUPLICATE KEY UPDATE role=VALUES(role), is_active=1, password_hash=VALUES(password_hash);

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES ('RBAC Test AUDITOR', 'rbac-test-auditor@crsholdings.ph', '$2y$12$CDzwbAKOW4hd0/42H7Fq4eYXJ49tu1QnXspqriSuFM4nWsXP7MoYi', 'AUDITOR', 1)
ON DUPLICATE KEY UPDATE role=VALUES(role), is_active=1, password_hash=VALUES(password_hash);

-- TEARDOWN: DELETE FROM users WHERE email LIKE 'rbac-test-%@crsholdings.ph';
