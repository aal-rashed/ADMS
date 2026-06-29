<?php
/**
 * Split PHP sessions: Admin and Alumni use different session cookie names so
 * both portals can be open in the same browser without overwriting each other.
 */
declare(strict_types=1);

const ADMS_SESSION_ADMIN  = 'ADMS_ADMIN';
const ADMS_SESSION_ALUMNI = 'ADMS_ALUMNI';

function adms_session_start_admin(): void
{
    if (session_status() === PHP_SESSION_ACTIVE && session_name() === ADMS_SESSION_ADMIN) {
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_name(ADMS_SESSION_ADMIN);
    session_start();
}

function adms_session_start_alumni(): void
{
    if (session_status() === PHP_SESSION_ACTIVE && session_name() === ADMS_SESSION_ALUMNI) {
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_name(ADMS_SESSION_ALUMNI);
    session_start();
}

function adms_try_start_portal(string $which): bool
{
    if ($which === 'admin') {
        if (empty($_COOKIE[ADMS_SESSION_ADMIN])) {
            return false;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_name(ADMS_SESSION_ADMIN);
        session_start();
        return !empty($_SESSION['admin_id']);
    }
    if ($which === 'alumni') {
        if (empty($_COOKIE[ADMS_SESSION_ALUMNI])) {
            return false;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_name(ADMS_SESSION_ALUMNI);
        session_start();
        return !empty($_SESSION['alumni_id']);
    }
    return false;
}

/**
 * Community lives in both portals: start the correct cookie for this request.
 * Pass ?portal=admin|alumni on the page URL, or the same value as JSON key "portal"
 * / form field "portal" from fetch, so the handler binds to the intended session when both cookies exist.
 *
 * @return 'admin'|'alumni'|'' active portal label (empty if not logged in either)
 */
function adms_session_start_community(?string $portalHint = null): string
{
    $hint = in_array($portalHint, ['admin', 'alumni'], true) ? $portalHint : null;

    if ($hint === 'alumni' && adms_try_start_portal('alumni')) {
        return 'alumni';
    }
    if ($hint === 'admin' && adms_try_start_portal('admin')) {
        return 'admin';
    }

    if ($hint === null) {
        if (adms_try_start_portal('alumni')) {
            return 'alumni';
        }
        if (adms_try_start_portal('admin')) {
            return 'admin';
        }
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_name(ADMS_SESSION_ALUMNI);
    session_start();
    return '';
}

/** Peek the admin session cookie without leaving the alumni session active. */
function adms_admin_session_has_login(): bool
{
    if (empty($_COOKIE[ADMS_SESSION_ADMIN])) {
        return false;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_name(ADMS_SESSION_ADMIN);
    session_start();
    $ok = isset($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] > 0;
    session_write_close();
    return $ok;
}
