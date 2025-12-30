# Requirements Document

## Introduction

This document specifies the requirements for replacing the built-in nginx basic authentication with a custom sign-in page, password change functionality, and basic user management for the RpiDNS application. The current system uses nginx's `auth_basic` directive with an `.htpasswd` file, which provides minimal security and no user-friendly interface. The new system will provide a modern login experience with session management, password change capabilities, and basic user administration.

## Glossary

- **Authentication_System**: The backend PHP service responsible for validating user credentials, managing sessions, and handling user operations
- **Login_Page**: The Vue.js frontend component that provides the sign-in interface
- **Session**: A server-side record that tracks authenticated user state using secure tokens
- **User_Manager**: The administrative interface for creating, editing, and deleting user accounts
- **Password_Hasher**: The component responsible for securely hashing and verifying passwords using bcrypt

## Requirements

### Requirement 1: User Login

**User Story:** As a user, I want to sign in through a dedicated login page, so that I can securely access the RpiDNS admin interface.

#### Acceptance Criteria

1. WHEN a user navigates to the admin interface without an active session, THE Authentication_System SHALL redirect them to the Login_Page
2. WHEN a user submits valid credentials on the Login_Page, THE Authentication_System SHALL create a session and redirect to the admin interface
3. WHEN a user submits invalid credentials, THE Authentication_System SHALL display an error message without revealing whether the username or password was incorrect
4. WHEN a user has an active session, THE Authentication_System SHALL allow access to protected resources
5. IF a session token is invalid or expired, THEN THE Authentication_System SHALL redirect the user to the Login_Page

### Requirement 2: User Logout

**User Story:** As a user, I want to sign out of the application, so that I can secure my session when I'm done.

#### Acceptance Criteria

1. WHEN a user clicks the logout button, THE Authentication_System SHALL invalidate the current session
2. WHEN a session is invalidated, THE Authentication_System SHALL redirect the user to the Login_Page
3. THE Login_Page SHALL display a logout confirmation message after successful logout

### Requirement 3: Password Change

**User Story:** As an authenticated user, I want to change my password, so that I can maintain account security.

#### Acceptance Criteria

1. WHEN an authenticated user accesses the password change form, THE Authentication_System SHALL require the current password for verification
2. WHEN a user submits a valid current password and new password, THE Authentication_System SHALL update the password hash in the database
3. WHEN a user submits an incorrect current password, THE Authentication_System SHALL reject the change and display an error
4. THE Authentication_System SHALL enforce minimum password requirements: at least 8 characters
5. WHEN a password is successfully changed, THE Authentication_System SHALL invalidate all other sessions for that user

### Requirement 4: User Management

**User Story:** As an administrator, I want to manage user accounts, so that I can control who has access to the system.

#### Acceptance Criteria

1. WHEN an administrator accesses the User_Manager, THE Authentication_System SHALL display a list of all users
2. WHEN an administrator creates a new user, THE Authentication_System SHALL store the username and hashed password
3. WHEN an administrator deletes a user, THE Authentication_System SHALL remove the user and invalidate all their sessions
4. WHEN an administrator resets a user's password, THE Authentication_System SHALL generate a new password and invalidate existing sessions
5. THE Authentication_System SHALL prevent deletion of the last administrator account
6. THE User_Manager SHALL only be accessible to users with administrator privileges

### Requirement 5: Session Security

**User Story:** As a system administrator, I want sessions to be secure, so that unauthorized access is prevented.

#### Acceptance Criteria

1. THE Authentication_System SHALL generate cryptographically secure session tokens
2. THE Authentication_System SHALL store session tokens as HTTP-only cookies
3. WHEN a session has been inactive for 24 hours, THE Authentication_System SHALL automatically expire it
4. THE Authentication_System SHALL store password hashes using bcrypt with appropriate cost factor
5. THE Authentication_System SHALL rate-limit login attempts to prevent brute force attacks

### Requirement 6: Database Storage

**User Story:** As a developer, I want user data stored in SQLite, so that it integrates with the existing database infrastructure.

#### Acceptance Criteria

1. THE Authentication_System SHALL store user accounts in a `users` table in the SQLite database
2. THE Authentication_System SHALL store active sessions in a `sessions` table in the SQLite database
3. WHEN the application starts, THE Authentication_System SHALL create required tables if they do not exist
4. THE Authentication_System SHALL migrate existing `.htpasswd` users to the database on first run

### Requirement 8: Database Schema Versioning

**User Story:** As a developer, I want the database schema to be versioned and upgradeable, so that existing installations can be updated without data loss.

#### Acceptance Criteria

1. THE Authentication_System SHALL check the current database schema version on startup
2. WHEN the database schema version is lower than the required version, THE Authentication_System SHALL execute incremental migration scripts
3. THE Authentication_System SHALL increment the DBVersion constant after adding authentication tables
4. WHEN migrations are applied, THE Authentication_System SHALL update the schema version in the database
5. IF a migration fails, THEN THE Authentication_System SHALL rollback changes and log the error
6. THE Authentication_System SHALL preserve existing data during schema upgrades

### Requirement 7: Frontend Integration

**User Story:** As a user, I want the login experience to match the existing application style, so that the interface feels consistent.

#### Acceptance Criteria

1. THE Login_Page SHALL use the same Bootstrap styling as the existing admin interface
2. THE Login_Page SHALL be responsive and work on mobile devices
3. WHEN authentication fails, THE Login_Page SHALL display error messages inline without page reload
4. THE Authentication_System SHALL expose a logout button in the main application header
