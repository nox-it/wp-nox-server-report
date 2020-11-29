<?php

    /**
     * Plugin Name: Server Report
     * Description: jSAS Development Server Report for WordPress instalations
     * Plugin URI:  https://github.com/nox-it/wp-nox-server-report
     * Author:      NOX IT
     * Author URI:  https://github.com/nox-it
     * License:     GNU General Public License v2 or later
     * License URI: http://www.gnu.org/licenses/gpl-2.0.html
     * Version:     1.0.0
     */

    defined('ABSPATH') or die();

    defined('NOX_SR_TOKEN')         or define('NOX_SR_TOKEN',         '');
    defined('NOX_SR_SITE_NAME')     or define('NOX_SR_SITE_NAME',     'NOX - Server Report');
    defined('NOX_SR_SNAPSHOT_NAME') or define('NOX_SR_SNAPSHOT_NAME', 'nox-server-report');

    /**
     * @return array
     *
     * @throws Exception
     */
    function nox_sr_generate_report(): array
    {
        global $wp_version, $wpdb;

        $snapshotName = NOX_SR_SNAPSHOT_NAME;

        $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));

        $m = $now->format('m');
        $y = $now->format('Y');

        $report  = [];
        $plugins = get_plugins();

        $report[] = ['type' => 'cms', 'name' => 'WordPress', 'version' => $wp_version, 'description' => 'WordPress is open source software you can use to create a beautiful website, blog, or app.'];

        if (is_array($plugins) && count($plugins) > 0) {
            foreach ($plugins as $plugin) {
                if (isset($plugin['Name'], $plugin['Version'], $plugin['Description'], $plugin['PluginURI'])) {
                    $report[] = ['type' => 'plugin', 'uri' => $plugin['PluginURI'], 'name' => $plugin['Name'], 'version' => $plugin['Version'], 'description' => $plugin['Description']];
                }
            }
        }

        $webServer     = $_SERVER['SERVER_SOFTWARE'];
        $isNginx       = preg_match('/nginx/', strtolower($webServer));
        $webServerName = (($isNginx) ? 'NGINX' : 'Apache HTTPD');

        $report[] = ['type' => 'server', 'name' => 'Linux (Ubuntu)', 'version' => php_uname(), 'description' => ''];
        $report[] = ['type' => 'web-server', 'name' => "Web Server ({$webServerName})", 'version' => $webServer, 'description' => ''];
        $report[] = ['type' => 'php', 'name' => 'PHP', 'version' => PHP_VERSION, 'description' => ''];

        $dbVersion = $wpdb->get_results("SHOW VARIABLES LIKE 'version'");

        if (is_array($dbVersion) && isset($dbVersion[0], $dbVersion[0]->Value)) {
            $dbVersion = $dbVersion[0]->Value;
        } else {
            $dbVersion = '';
        }

        $isMariaDb = preg_match('/mariadb/', strtolower($dbVersion));
        $dbName    = (($isMariaDb) ? 'MariaDB' : 'MySQL');

        $report[] = ['type' => 'db', 'name' => $dbName, 'version' => $dbVersion, 'description' => ''];
        $report[] = ['type' => 'snapshot', 'name' => 'Snapshot', 'version' => "{$snapshotName}-{$m}-{$y}", 'description' => ''];

        $report = ['name' => NOX_SR_SITE_NAME, 'siteName' => get_bloginfo('name'), 'url' => site_url('/'), 'date' => date('d/m/Y H:i'), 'entries' => $report];

        return $report;
    }

    add_action(
        'rest_api_init',
        static function () {
            register_rest_route(
                'nox/v1',
                '/server-report',
                [
                    'methods' => 'POST',
                    'callback' => static function (WP_REST_Request $request) {
                        $token       = NOX_SR_TOKEN;
                        $sendedToken = (string)$request->get_param('nox-sr-token');

                        if (!empty($token) && $token === $sendedToken) {
                            return new WP_REST_Response(
                                nox_sr_generate_report(),
                                200
                            );
                        }

                        return new WP_REST_Response([], 401);
                    }
                ]
            );

            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');

            add_filter(
                'rest_pre_serve_request',
                static function ($value) {
                    header('Access-Control-Allow-Origin: *');
                    header('Access-Control-Allow-Methods: GET, POST');
                    header('Access-Control-Allow-Credentials: true');
                    header('Access-Control-Allow-Headers: Authorization, Content-Type');

                    return $value;
                }
            );
        }
    );

