<?php
/**
 * (c) Vadim Pavlov 2020 - 2026
 * ApiAuth - Server-side authentication gate for the whole rpidata.php API.
 *
 * rpidata.php is reachable directly over HTTP (nginx passes /rpi_admin/*.php
 * straight to php-fpm and performs no authentication of its own), so the API
 * itself must be the gate. requireApiSession() is called ONCE, at the very top
 * of rpidata.php, before the database is opened and before any SQL is built, so
 * that every endpoint - data queries, settings, downloads, assets, block/allow
 * lists, RPZ feeds and DB import - is protected by default. A new endpoint added
 * to the dispatcher inherits the gate instead of having to remember it.
 *
 * The session is validated with AuthService::verifySession(), which reads the
 * `rpidns_session` cookie (HttpOnly, SameSite=Strict) and returns null for a
 * missing, unknown or expired session. On failure the request is answered with
 * HTTP 401 and a JSON error envelope and terminated, which is exactly what the
 * frontend's axios interceptor turns into a "session expired" redirect to the
 * login screen (see rpidns-frontend/src/composables/useApi.js).
 *
 * @see ResearchAuth.php - the same guard scoped to the Research endpoints, kept
 *      in place as defense in depth for those handlers.
 */

require_once "/opt/rpidns/www/rpi_admin/auth.php";

if (!function_exists('requireApiSession')) {
    /**
     * Require a valid authenticated session for an API request.
     *
     * Must run before any database access or system command. On a
     * missing/invalid/expired session it sends HTTP 401 with
     * {"status":"error","reason":"authentication required"} and exits.
     *
     * @param AuthService|null $authService Optional AuthService instance
     *                                      (primarily to aid testing); a new
     *                                      instance is created when not provided.
     * @return array Authenticated user info:
     *               ['id', 'username', 'is_admin', 'session_id', 'expires_at'].
     */
    function requireApiSession($authService = null) {
        $auth = $authService ?? new AuthService();

        $user = $auth->verifySession();

        if ($user === null) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(["status" => "error", "reason" => "authentication required"]);
            exit;
        }

        return $user;
    }
}
