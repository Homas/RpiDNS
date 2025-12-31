# Implementation Plan: User Authentication System

## Overview

This implementation plan converts the design into discrete coding tasks for adding a custom authentication system to RpiDNS. The tasks are organized to build incrementally, starting with database schema and backend services, then frontend components, and finally integration and testing.

## Tasks

- [x] 1. Set up database schema and migration service
  - [x] 1.1 Create db_migrate.php with DbMigration class
    - Implement getSchemaVersion() to read current version from schema_version table
    - Implement setSchemaVersion() to update version after migration
    - Implement migrate() to run pending migrations
    - Implement rollback() for failure handling
    - _Requirements: 8.1, 8.2, 8.4, 8.5_

  - [x] 1.2 Implement v1 to v2 migration for auth tables
    - Create users table with id, username, password_hash, is_admin, created_at, updated_at
    - Create sessions table with id, user_id, token, created_at, expires_at, ip_address, user_agent
    - Create login_attempts table for rate limiting
    - Create schema_version table if not exists
    - Update DBVersion constant in rpidns_vars.php to 2
    - _Requirements: 6.1, 6.2, 6.3, 8.3_

  - [x] 1.3 Implement htpasswd user import
    - Parse existing /opt/rpidns/conf/.htpasswd file
    - Import users with bcrypt or convert MD5 hashes
    - Set first imported user as admin
    - _Requirements: 6.4_

  - [ ]* 1.4 Write property tests for migration service
    - **Property 19: Incremental Migration Updates Schema**
    - **Property 20: Migration Rollback on Failure**
    - **Property 21: Migration Preserves Existing Data**
    - **Validates: Requirements 8.2, 8.4, 8.5, 8.6**

- [x] 2. Implement core authentication service
  - [x] 2.1 Create auth.php with AuthService class
    - Implement generateToken() for secure session tokens (64 hex chars)
    - Implement hashPassword() using bcrypt with cost 12
    - Implement verifyPassword() using password_verify()
    - _Requirements: 5.1, 5.4_

  - [x] 2.2 Implement login functionality
    - Implement login() to validate credentials and create session
    - Store session in database with expiration (24 hours)
    - Set HTTP-only session cookie
    - Return generic error for invalid credentials
    - _Requirements: 1.2, 1.3, 5.2_

  - [x] 2.3 Implement session verification
    - Implement verifySession() to check token validity
    - Check session expiration (24 hours)
    - Return user info for valid sessions
    - _Requirements: 1.4, 1.5, 5.3_

  - [x] 2.4 Implement logout functionality
    - Implement logout() to delete session from database
    - Clear session cookie
    - _Requirements: 2.1, 2.2_

  - [x] 2.5 Implement rate limiting
    - Track failed login attempts by IP in login_attempts table
    - Block after 5 failed attempts in 15 minutes
    - Clean up old attempts periodically
    - _Requirements: 5.5_

  - [ ]* 2.6 Write property tests for authentication
    - **Property 1: Invalid Session Redirects to Login**
    - **Property 2: Valid Credentials Create Session**
    - **Property 3: Error Message Uniformity**
    - **Property 4: Valid Session Grants Access**
    - **Property 5: Logout Invalidates Session**
    - **Property 15: Session Tokens Are Cryptographically Secure**
    - **Property 16: Session Expiration After 24 Hours**
    - **Property 17: Rate Limiting Blocks Excessive Attempts**
    - **Validates: Requirements 1.1-1.5, 2.1, 5.1, 5.3, 5.5**

- [x] 3. Implement password management
  - [x] 3.1 Implement password change functionality
    - Implement changePassword() requiring current password verification
    - Validate new password minimum length (8 chars)
    - Update password hash in database
    - Invalidate all other sessions for user
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

  - [ ]* 3.2 Write property tests for password management
    - **Property 6: Password Change Requires Current Password**
    - **Property 7: Password Change Round-Trip**
    - **Property 8: Password Minimum Length Enforced**
    - **Property 9: Password Change Invalidates Other Sessions**
    - **Validates: Requirements 3.1-3.5**

- [x] 4. Implement user management (admin)
  - [x] 4.1 Implement user listing
    - Implement listUsers() to return all users (admin only)
    - Exclude password hashes from response
    - _Requirements: 4.1, 4.6_

  - [x] 4.2 Implement user creation
    - Implement createUser() with username, password, isAdmin
    - Validate unique username
    - Store bcrypt hashed password
    - _Requirements: 4.2_

  - [x] 4.3 Implement user deletion
    - Implement deleteUser() to remove user and sessions
    - Prevent deletion of last admin account
    - _Requirements: 4.3, 4.5_

  - [x] 4.4 Implement password reset
    - Implement resetPassword() to generate new random password
    - Invalidate all sessions for user
    - Return new password to admin
    - _Requirements: 4.4_

  - [ ]* 4.5 Write property tests for user management
    - **Property 10: User Listing Returns All Users**
    - **Property 11: User Creation Stores Bcrypt Hash**
    - **Property 12: User Deletion Removes User and Sessions**
    - **Property 13: Password Reset Invalidates Sessions**
    - **Property 14: Admin-Only Access to User Management**
    - **Validates: Requirements 4.1-4.6**

- [x] 5. Checkpoint - Backend complete
  - Ensure all backend tests pass, ask the user if questions arise.

- [x] 6. Create frontend login page
  - [x] 6.1 Create LoginPage.vue component
    - Create login form with username and password fields
    - Style with Bootstrap to match existing admin interface
    - Implement form validation
    - _Requirements: 7.1, 7.2_

  - [x] 6.2 Implement login API integration
    - POST credentials to /rpi_admin/auth.php?action=login
    - Handle success: store session, redirect to app
    - Handle error: display inline error message
    - Handle rate limiting: show appropriate message
    - _Requirements: 1.2, 1.3, 7.3_

  - [x] 6.3 Implement session check on app load
    - Check session validity on App.vue mount
    - Redirect to login if no valid session
    - Show loading state during check
    - _Requirements: 1.1, 1.4, 1.5_

- [x] 7. Integrate authentication into main app
  - [x] 7.1 Add logout button to App.vue header
    - Add logout icon/button in header area
    - Call logout API on click
    - Redirect to login page after logout
    - _Requirements: 2.1, 2.2, 7.4_

  - [x] 7.2 Add authentication state management
    - Store current user info in app state
    - Track admin status for conditional UI
    - _Requirements: 1.4, 4.6_

  - [x] 7.3 Protect API calls with session verification
    - Update useApi.js composable to handle 401 responses
    - Redirect to login on session expiration
    - _Requirements: 1.5_

- [x] 8. Create password change UI
  - [x] 8.1 Create PasswordChange.vue modal component
    - Form with current password, new password, confirm password
    - Client-side validation for password match and length
    - _Requirements: 3.1, 3.4_

  - [x] 8.2 Integrate password change into Settings
    - Add "Change Password" button to Settings tab
    - Open PasswordChange modal on click
    - Show success/error messages
    - _Requirements: 3.2, 3.3_

- [x] 9. Create user management UI
  - [x] 9.1 Create UserManager.vue component
    - Table listing all users with username, admin status, created date
    - Action buttons for reset password and delete
    - Only visible to admin users
    - _Requirements: 4.1, 4.6_

  - [x] 9.2 Create AddUser.vue modal component
    - Form with username, password, confirm password, admin checkbox
    - Validation for required fields and password match
    - _Requirements: 4.2_

  - [x] 9.3 Implement user management actions
    - Delete user with confirmation dialog
    - Reset password with new password display
    - Prevent last admin deletion
    - _Requirements: 4.3, 4.4, 4.5_

  - [x] 9.4 Add UserManager to Admin tabs
    - Add new "Users" tab in AdminTabs.vue
    - Only show tab for admin users
    - _Requirements: 4.6_

- [x] 10. Update nginx configuration
  - [x] 10.1 Remove basic auth from nginx.conf.template
    - Remove auth_basic and auth_basic_user_file directives
    - Keep location blocks for /rpi_admin
    - _Requirements: 1.1_

  - [x] 10.2 Add auth.php to allowed PHP locations
    - Ensure auth.php is accessible without authentication
    - Verify other PHP files still work
    - _Requirements: 1.2_

- [x] 11. Checkpoint - Integration complete
  - Ensure all tests pass, ask the user if questions arise.

- [-] 12. Write htpasswd migration property test
  - [ ]* 12.1 Write property test for htpasswd import
    - **Property 18: Htpasswd Migration Imports Users**
    - **Validates: Requirements 6.4**

- [x] 13. Final integration testing
  - [x] 13.1 Test complete authentication flow
    - Test login with migrated htpasswd user
    - Test logout and session expiration
    - Test password change
    - Test user management (create, delete, reset)
    - _Requirements: All_

- [ ] 14. Final checkpoint
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- Backend (PHP) should be completed before frontend integration
- The existing .htpasswd file will be preserved as backup after migration
