<?php
/**
 * (c) Vadim Pavlov 2020 - 2026
 * ResearchAuth - Server-side authentication guard for the Research API.
 *
 * Every Research_API endpoint (research_unique, research_tables, research_sql,
 * research_tool) MUST call requireResearchSession() before performing any
 * database query or system command. The guard validates the caller's session
 * via AuthService::verifySession() (which reads the `rpidns_session` cookie).
 * When the session is missing, invalid, or expired it responds with a 401 and a
 * JSON error envelope and terminates the request, so no protected data is
 * returned and no requested operation is performed.
 *
 * On success it returns the authenticated user array (including `session_id`,
 * needed later for the rejection audit log).
 *
 * @see .kiro/specs/research-tools/design.md ("Auth guard")
 * Requirements: 1.7, 9.1, 9.4
 */

require_once "/opt/rpidns/www/rpi_admin/auth.php";

if (!function_exists('requireResearchSession')) {
    /**
     * Require a valid authenticated session for a Research_API request.
     *
     * Runs before any DB query or system command. On a missing/invalid/expired
     * session it sends HTTP 401 with {"status":"error","reason":"authentication
     * required"} and exits. On success it returns the authenticated user array.
     *
     * @param AuthService|null $authService Optional AuthService instance
     *                                      (primarily to aid testing); a new
     *                                      instance is created when not provided.
     * @return array Authenticated user info:
     *               ['id', 'username', 'is_admin', 'session_id', 'expires_at'].
     */
    function requireResearchSession($authService = null) {
        $auth = $authService ?? new AuthService();

        // verifySession() with no argument reads the `rpidns_session` cookie and
        // returns null on a missing/invalid/expired session.
        $user = $auth->verifySession();

        if ($user === null) {
            http_response_code(401);
            echo json_encode(["status" => "error", "reason" => "authentication required"]);
            exit;
        }

        return $user;
    }
}
