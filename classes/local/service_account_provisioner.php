<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Convergent provisioning of the dedicated Raison service identity.
 *
 * @package   local_corolair
 * @copyright 2025 Raison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_corolair\local;

/**
 * Creates and repairs the non-administrator account that owns the web service token.
 *
 * The token used to be minted for the site administrator who consented to setup, so
 * Moodle evaluated every Raison call with that administrator's capabilities -- including
 * everything the integration never asks for. Nothing in the function allowlist required
 * that: the allowlist bounds which functions the token may call, while the token owner
 * bounds what each of those calls is permitted to see and do. This class supplies an owner
 * whose capabilities are exactly the second bound and nothing more.
 *
 * Every operation is convergent in the same sense as role_provisioner: it drives the
 * account, the role, the grants and the authorised-user row to the intended end state from
 * whatever state it finds, and produces the same result when run repeatedly. That matters
 * because the hourly rotation task is the repair path -- an administrator who suspends the
 * account, deletes the role assignment or overrides a capability to prevent is corrected on
 * the next run rather than breaking the integration permanently.
 */
final class service_account_provisioner {
    /** Username of the service account. */
    public const USERNAME = 'corolair_webservice';

    /**
     * Address of the service account.
     *
     * A real, monitored Raison address rather than an unroutable placeholder, because the
     * audience for this field is a site administrator who finds an account in Site
     * administration > Users that nobody at their institution created. An address they can
     * write to answers "what is this?" on the spot; a synthetic one invites them to delete
     * it. The account still never receives mail from the site -- emailstop is set, so
     * Moodle's message layer will not deliver to it, and maildisplay hides it from ordinary
     * users.
     */
    public const EMAIL = 'contact@raison.is';

    /**
     * Address used when the one above is already taken on this site.
     *
     * There is no unique index on user.email, so a collision is not an error Moodle would
     * raise -- it is a silent degradation: get_complete_user_data('email', ...) backs the
     * forgot-password flow with get_record(), which returns nothing useful once two rows
     * match. Handing this account a distinct address instead means a Raison employee who
     * genuinely has an account on a customer's Moodle keeps working.
     *
     * The .invalid TLD is reserved by RFC 2606 and can never resolve, but the value still
     * has to survive validate_email() inside core_user::validate(), which requires a dot
     * and a plausible TLD. A bare "corolair_webservice@invalid" would be cleaned away with
     * a debugging() notice.
     */
    public const FALLBACK_EMAIL = 'corolair_webservice@invalid.invalid';

    /**
     * Addresses this plugin considers its own to rewrite.
     *
     * Anything else on the account was put there by an administrator and is left alone, the
     * same way the display name and description are.
     */
    private const OWNED_EMAILS = [self::EMAIL, self::FALLBACK_EMAIL];

    /**
     * Authentication plugin of the service account.
     *
     * Load-bearing, in two independent ways, and not interchangeable with 'manual':
     *
     * - auth_plugin_webservice::user_login() returns false unconditionally, so the account
     *   has no interactive login path at all.
     * - require_login() exempts $USER->auth === 'webservice' from the site policy gate. On
     *   a site with a site policy configured, any other auth method would make every
     *   function that calls validate_context() throw sitepolicynotagreed.
     *
     * 'nologin' is the one value that must never be used: the web service layer refuses it
     * outright, for both function calls and the file scripts. Note also that token
     * authentication does not require auth_webservice to be an *enabled* auth plugin --
     * that check only applies to username/password token issuance.
     */
    public const AUTH = 'webservice';

    /** Shortname of the role that carries the service account's capabilities. */
    public const ROLE_SHORTNAME = 'corolair_service';

    /**
     * Context levels the service role may be assigned at.
     *
     * System only, deliberately. Making it course-assignable would look like scoping while
     * actually creating a second, unaudited way to hand these capabilities out.
     */
    public const CONTEXTLEVELS = [CONTEXT_SYSTEM];

    /** External service shortname. */
    public const SERVICE_SHORTNAME = 'corolair_rest';

    /**
     * Capabilities the service account always holds.
     *
     * Every entry is here because a specific allowlisted function requires it. Nothing is
     * included "to be safe": a capability that cannot be traced to a function is a finding
     * waiting to happen. The set is read-only apart from local/corolair:assignmanagerrole,
     * see the note on WRITE_CAPABILITIES.
     */
    public const READ_CAPABILITIES = [
        // Granted explicitly rather than relied upon. Without it the web-service layer
        // refuses every call before it looks at the service or the function at all, and a
        // stock Moodle happens to supply it through the authenticated user role -- which is
        // exactly the kind of thing a hardened site strips. Depending on someone else's role
        // definition for the capability that makes the integration work at all is a bet with
        // no upside; the capability is captype read and carries no risk flags.
        //
        // Worth knowing when diagnosing: its absence fails in a lopsided way, because
        // webservice/pluginfile.php never checks the protocol capability. File downloads
        // keep working while every function call returns accessexception.
        'webservice/rest:use',
        // The load-bearing one. require_login() -> is_viewing() -> has_capability() on this
        // capability is what lets an unenrolled account reach a course at all; without it
        // every course-scoped function throws "Not enrolled" and the integration sees
        // nothing whatsoever.
        'moodle/course:view',
        'moodle/course:viewhiddencourses',
        // Replaces moodle/course:update, which get_section_availability accepts as one of
        // two disjuncts for returning the raw availability rule. The administrator token
        // always satisfied the first disjunct; a service account needs the second, or the
        // raw rule silently becomes null instead of erroring.
        'moodle/course:viewhiddensections',
        'moodle/course:viewhiddenactivities',
        // Not a duplicate of the two above, and easy to think it is. Hiding an activity with
        // the eye icon and restricting it with an availability rule are separate mechanisms
        // evaluated in separate branches of cm_info, and a restriction set to "hide entirely"
        // makes core_course_get_contents omit the module from the payload altogether rather
        // than return it marked unavailable. A whole section carrying such a rule disappears
        // the same way, taking every module in it.
        //
        // What this capability buys is sync *completeness*, not protection from mass archival.
        // Worth being precise about, because the two are easy to conflate and the difference
        // is what an auditor asks about. Raison does not read "absent" as "deleted" on a token
        // that cannot see everything: its content sync calls get_integration_status per course
        // first, and a run that comes back unprivileged may add and update but may never
        // conclude that anything was deleted. So dropping this capability does not destroy
        // content -- it stops restricted material being ingested at all, and it stops removals
        // being applied to those courses, so genuinely deleted material lingers instead.
        //
        // The administrator token held this implicitly, so granting it preserves existing
        // behaviour rather than widening it -- but it does mean the account reads content
        // restricted to a subset of learners, which the disclosure says in as many words.
        // get_integration_status::VISIBILITY_CAPABILITIES reports on exactly this list, and a
        // unit test keeps the two in step.
        'moodle/course:ignoreavailabilityrestrictions',
        'moodle/course:viewparticipants',
        'moodle/category:viewhiddencategories',
        // Required unconditionally by core_enrol_get_enrolled_users_with_capability, and
        // declared by neither that function's service entry nor its documentation.
        'moodle/role:review',
        'moodle/site:accessallgroups',
        'moodle/user:viewdetails',
        'moodle/user:viewhiddendetails',
        // Identity fields are filtered per-capability when the read is course-scoped, so
        // without this email is returned by core_user_get_users_by_field but not by
        // core_enrol_get_enrolled_users -- an inconsistency that is easy to miss in QA.
        //
        // Note what is deliberately *not* here: moodle/user:viewalldetails. It gates username,
        // idnumber, institution, department and the auth/confirmed/lang/theme/mailformat block,
        // and nothing in the integration reads any of them. Raison resolves people by email
        // alone, which the two capabilities in this pair already cover.
        'moodle/site:viewuseridentity',
        // The second, independent route to the same field, and not redundant with the one
        // above. user_get_user_details() returns email when the identity-field list contains
        // it -- which is what viewuseridentity unlocks -- *or* when this capability is held.
        // Neither route is affected by dropping viewalldetails: email is not one of the fields
        // that capability gates.
        // The first route depends on $CFG->showuseridentity still listing email, which a
        // privacy-minded administrator may well have changed; the second does not depend on
        // site configuration at all. Raison falls back to matching Moodle accounts by email
        // when it has no stored Moodle user ID, and an absent email does not raise anything:
        // the match simply finds nobody and the person sees no courses. Despite being
        // declared captype write, it permits no write whatsoever -- it carries no risk flags,
        // and core's own comment beside the check marks it for deprecation as a read gate.
        'moodle/course:useremail',
        // Completion needs report/progress:view for the activity-level function and
        // report/completion:view for the course-level one. The two are not interchangeable,
        // and only the second is documented by core, so the first is easy to miss.
        'report/progress:view',
        'report/completion:view',
        // Module view capabilities. These gate both the *_get_*_by_courses reads and, more
        // importantly, the component pluginfile callbacks that serve file downloads.
        'mod/resource:view',
        'mod/lesson:view',
        'mod/lti:view',
        'mod/book:read',
        'mod/folder:view',
        'mod/page:view',
        'mod/url:view',
        'local/corolair:viewroles',
        // Technically a write, and kept unconditionally on purpose: it is how a trainer
        // invited from Raison is onboarded, which must work on a default installation. It
        // is safe to grant because the escalation loop is closed inside this plugin --
        // assign_manager_role can only ever assign one fixed role, that role holds only
        // local/corolair capabilities, and the function calls role_assign() directly, so
        // it needs neither moodle/role:assign nor a role_allow_assign entry.
        'local/corolair:assignmanagerrole',
    ];

    /**
     * The only *core* write capabilities the integration holds.
     *
     * Kept as a separate constant from the read set even though both are granted the same
     * way, because the distinction is the one a reviewer asks about first and it should not
     * have to be reconstructed by reading Moodle's capability definitions. Everything here
     * exists for exam placement: creating, renaming and deleting the External tool activity
     * that carries a Raison exam. Nothing else in the integration writes to a course.
     *
     * The blast radius is bounded by the functions rather than by the capabilities. Raison
     * can only reach these through local_corolair_create_exam_placement,
     * local_corolair_manage_exam_placement and local_corolair_delete_exam_placement, and
     * delete_exam_placement resolves its target through the {lti} table with MUST_EXIST, so
     * it can only ever remove an LTI activity -- never an arbitrary course module.
     */
    public const WRITE_CAPABILITIES = [
        'moodle/course:manageactivities',
        'mod/lti:addinstance',
        // Required in addition to mod/lti:addinstance since Moodle 4.3 for a preconfigured
        // tool type, which is what the Raison exam placement always creates.
        'mod/lti:addpreconfiguredinstance',
        'mod/lti:addcoursetool',
    ];

    /**
     * Capabilities the service role must not hold, and once did.
     *
     * ensure_capabilities() is otherwise purely additive, so deleting an entry from
     * READ_CAPABILITIES stops granting it to new installs while leaving every existing
     * role_capabilities row exactly where it was. Naming the removal here is what actually
     * withdraws it -- and keeps withdrawing it, which matters for a site restored from a
     * backup taken before the removal, or one whose role was rebuilt by hand.
     *
     * Deliberately a list of named removals rather than "delete every row not declared
     * above". The convergent version would also strip capabilities an administrator granted
     * this role on purpose, which is a decision this class has no business making.
     *
     * Entries are permanent. Once a capability is here it stays here: the whole point is to
     * keep withdrawing it from sites that have not converged yet, and there is no point at
     * which every such site is known to be gone.
     */
    public const REVOKED_CAPABILITIES = [
        // Gates username, idnumber, institution, department and the auth/confirmed/lang/theme/
        // mailformat/trackforums block. Nothing in the integration reads any of them: username
        // was the only one ever selected, by the email-matching lookup, and the invite form it
        // feeds discards it. Removed in 1.9.5 after an external audit asked which functions
        // required it and the answer turned out to be none.
        'moodle/user:viewalldetails',
    ];

    /**
     * Ensure the whole service identity exists and matches the current configuration.
     *
     * @return int The service account user ID.
     */
    public static function ensure(): int {
        $user = self::ensure_user();
        $roleid = self::ensure_role();
        self::ensure_capabilities($roleid);
        self::ensure_role_assignment($roleid, (int)$user->id);

        $serviceid = self::service_id();
        if ($serviceid > 0) {
            self::ensure_authorised($serviceid, (int)$user->id);
        }

        return (int)$user->id;
    }

    /**
     * Return the service account user ID without creating or repairing anything.
     *
     * Settings pages and the privacy provider must use this rather than ensure(): rendering
     * a page is not an appropriate moment to create a user, and a provider that mutated
     * state while answering a data request would be its own kind of bug.
     *
     * @return int User ID, or 0 when no usable account exists.
     */
    public static function locate(): int {
        global $CFG, $DB;

        $configured = (int)get_config('local_corolair', 'serviceaccountid');
        if ($configured > 0 && $DB->record_exists('user', ['id' => $configured, 'deleted' => 0])) {
            return $configured;
        }

        $user = $DB->get_record('user', [
            'username' => self::USERNAME,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ], 'id');

        return $user ? (int)$user->id : 0;
    }

    /**
     * Create the service account, or repair the existing one.
     *
     * Resolution deliberately tries a recorded ID before the username, because the two
     * cover different failures: an administrator may rename the account (the ID still
     * finds it), and configuration may be lost or restored from an older dump (the
     * username still finds it).
     *
     * @return \stdClass The service account user record.
     */
    public static function ensure_user(): \stdClass {
        global $CFG, $DB;

        $user = false;
        $configured = (int)get_config('local_corolair', 'serviceaccountid');
        if ($configured > 0) {
            $user = $DB->get_record('user', ['id' => $configured, 'deleted' => 0]);
        }
        if (!$user) {
            $user = $DB->get_record('user', [
                'username' => self::USERNAME,
                'mnethostid' => $CFG->mnet_localhost_id,
                'deleted' => 0,
            ]);
        }

        // A deleted account is never resurrected. delete_user() mangles the username
        // irreversibly and the row carries tombstone state that has no business being
        // reused; a fresh account is both cleaner and cheaper than trying to undo it.
        if (!$user) {
            $user = self::create_user();
        } else {
            $user = self::repair_user($user);
        }

        set_config('serviceaccountid', (int)$user->id, 'local_corolair');
        return $user;
    }

    /**
     * Insert the service account.
     *
     * @return \stdClass The created user record.
     */
    private static function create_user(): \stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/lib.php');

        $user = (object)[
            'username' => self::USERNAME,
            // The column default is 0, which is not the local host and would collide with
            // the (mnethostid, username) unique index in surprising ways.
            'mnethostid' => $CFG->mnet_localhost_id,
            'auth' => self::AUTH,
            'confirmed' => 1,
            'deleted' => 0,
            'suspended' => 0,
            // Belt and braces alongside the auth-based exemption in require_login().
            'policyagreed' => 1,
            // Never a valid hash, and self-documenting in a way that an empty string is not.
            'password' => AUTH_PASSWORD_NOT_CACHED,
            'email' => self::desired_email(0),
            'maildisplay' => 0,
            'emailstop' => 1,
            'firstname' => get_string('serviceaccountfirstname', 'local_corolair'),
            'lastname' => get_string('serviceaccountlastname', 'local_corolair'),
            'description' => get_string('serviceaccountdescription', 'local_corolair'),
            'descriptionformat' => FORMAT_PLAIN,
        ];

        // The event is left enabled on purpose. Suppressing it would hide the account from
        // observers that legitimately track user creation, and a synthetic account is
        // exactly the kind of thing an audit trail should record.
        $user->id = user_create_user($user, false, true);

        return $DB->get_record('user', ['id' => (int)$user->id], '*', MUST_EXIST);
    }

    /**
     * Restore the invariants an administrator may have broken on an existing account.
     *
     * The display name and description are left alone even when they differ: an
     * administrator may have relabelled the account, and that customisation should survive
     * a repair. Only the properties the integration actually depends on are reset.
     *
     * @param \stdClass $user Existing user record.
     * @return \stdClass The repaired user record.
     */
    private static function repair_user(\stdClass $user): \stdClass {
        global $CFG, $DB;

        $changes = [];
        if ((int)$user->suspended !== 0) {
            // Uninstall suspends rather than deletes, so this is the ordinary reinstall path.
            $changes['suspended'] = 0;
        }
        if ((int)$user->confirmed !== 1) {
            $changes['confirmed'] = 1;
        }
        if ($user->auth !== self::AUTH) {
            $changes['auth'] = self::AUTH;
        }
        if (empty($user->policyagreed)) {
            $changes['policyagreed'] = 1;
        }
        // Convergent so that accounts provisioned by an earlier release pick up the current
        // address, and so that a site which later gains a colliding user is stepped back off
        // it rather than left breaking that person's password reset.
        if (in_array($user->email, self::OWNED_EMAILS, true)) {
            $desired = self::desired_email((int)$user->id);
            if ($user->email !== $desired) {
                $changes['email'] = $desired;
            }
        }

        if (!$changes) {
            return $user;
        }

        require_once($CFG->dirroot . '/user/lib.php');
        $changes['id'] = (int)$user->id;
        user_update_user((object)$changes, false, true);

        return $DB->get_record('user', ['id' => (int)$user->id], '*', MUST_EXIST);
    }

    /**
     * Return the address to store on the service account.
     *
     * Deliberately not conditioned on $CFG->allowaccountssameemail. That setting governs
     * whether an administrator is *allowed* to create duplicates through the interface; it
     * does not make the consequences of one any better, and this account gains nothing from
     * sharing an address with a real person.
     *
     * @param int $excludeuserid The account being provisioned, so it does not collide with itself.
     * @return string
     */
    private static function desired_email(int $excludeuserid): string {
        global $DB;

        $select = 'email = :email AND deleted = 0';
        $params = ['email' => self::EMAIL];
        if ($excludeuserid > 0) {
            $select .= ' AND id <> :excluded';
            $params['excluded'] = $excludeuserid;
        }

        return $DB->record_exists_select('user', $select, $params) ? self::FALLBACK_EMAIL : self::EMAIL;
    }

    /**
     * Ensure the service role exists with the expected context levels.
     *
     * @return int The role ID.
     */
    public static function ensure_role(): int {
        global $DB;

        $role = false;
        $configured = (int)get_config('local_corolair', 'serviceroleid');
        if ($configured > 0) {
            // Preferred over the shortname so that an administrator who renames the
            // shortname gets the existing role repaired rather than a second, equally
            // capable role created beside it.
            $role = $DB->get_record('role', ['id' => $configured], 'id');
        }
        if (!$role) {
            $role = $DB->get_record('role', ['shortname' => self::ROLE_SHORTNAME], 'id');
        }

        if ($role) {
            $roleid = (int)$role->id;
        } else {
            $roleid = (int)create_role(
                get_string('serviceaccountrolename', 'local_corolair'),
                self::ROLE_SHORTNAME,
                get_string('serviceaccountroledescription', 'local_corolair')
            );
        }

        set_role_contextlevels($roleid, self::CONTEXTLEVELS);
        set_config('serviceroleid', $roleid, 'local_corolair');

        return $roleid;
    }

    /**
     * Grant every capability the service role is meant to hold, and withdraw the rest.
     *
     * Unlike role_provisioner::ensure_capability(), this writes through core's
     * assign_capability() instead of touching role_capabilities directly. That class has to
     * hand-roll the write because it is reachable from db/install.php, which core runs
     * before update_capabilities() has registered the plugin's own capabilities. Nothing
     * here ever runs that early -- provisioning happens at first registration and then from
     * the hourly task -- so core's API is both available and preferable: passing
     * $overwrite = true gives the same repair-a-prevented-grant convergence, and it
     * additionally *validates that the capability exists*, which turns a typo into a loud
     * failure instead of a silently dead row.
     *
     * Every run reapplies the whole set, which is what repairs a grant an administrator
     * removed or overrode to prevent, and withdraws everything in REVOKED_CAPABILITIES, which
     * is the only thing that retires a capability this role used to be granted.
     *
     * @param int $roleid Role to apply the capabilities to.
     * @return void
     */
    public static function ensure_capabilities(int $roleid): void {
        global $DB;

        $context = \context_system::instance();
        $capabilities = array_merge(self::READ_CAPABILITIES, self::WRITE_CAPABILITIES);

        foreach (self::filter_registered($capabilities) as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }

        // Withdraw what this role is no longer meant to hold. Ordered after the grants so the
        // two lists being wrongly allowed to overlap fails closed -- the capability ends up
        // revoked -- rather than silently granted. A unit test asserts they never overlap.
        //
        // Guarded on the row existing rather than calling unassign_capability() unconditionally.
        // Core deletes with delete_records(), which is a silent no-op on a site that never had
        // the capability, but it triggers capability_unassigned regardless -- and this runs
        // hourly on every install, so the unguarded version would emit that event forever, on
        // sites that converged long ago and on fresh installs that never held the capability at
        // all. filter_registered() stays in front of it because unassign_capability() throws a
        // coding_exception for a capability this site does not have installed.
        foreach (self::filter_registered(self::REVOKED_CAPABILITIES) as $capability) {
            $held = $DB->record_exists('role_capabilities', [
                'roleid' => $roleid,
                'contextid' => $context->id,
                'capability' => $capability,
            ]);
            if ($held) {
                unassign_capability($capability, $roleid, $context->id);
            }
        }

        // Capability changes are invisible to already-loaded access caches until the
        // context is marked dirty.
        $context->mark_dirty();
    }

    /**
     * Drop capabilities that no longer exist on this site.
     *
     * assign_capability() throws a coding_exception for an unknown capability, so a site
     * that has uninstalled an optional activity module would otherwise break the hourly
     * task outright. Skipping is the right failure mode: the corresponding functions cannot
     * be called on such a site anyway. Typos are caught by the unit test asserting that
     * every declared capability is registered, which runs against a full installation.
     *
     * @param array $capabilities Capability names.
     * @return array Names that exist in this installation.
     */
    private static function filter_registered(array $capabilities): array {
        global $DB;

        if (!$capabilities) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($capabilities, SQL_PARAMS_NAMED);
        $known = $DB->get_fieldset_select('capabilities', 'name', "name {$insql}", $params);

        return array_values(array_intersect($capabilities, $known));
    }

    /**
     * Assign the service role to the service account at system context.
     *
     * @param int $roleid Role ID.
     * @param int $userid Service account user ID.
     * @return void
     */
    public static function ensure_role_assignment(int $roleid, int $userid): void {
        global $DB;

        $context = \context_system::instance();
        $existing = $DB->record_exists('role_assignments', [
            'roleid' => $roleid,
            'userid' => $userid,
            'contextid' => $context->id,
        ]);
        if ($existing) {
            return;
        }

        role_assign($roleid, $userid, $context->id, '', 0);
    }

    /**
     * Authorise one user against the restricted service.
     *
     * validuntil is always left null, and that is not laziness. Core compares that column
     * inconsistently: the file scripts treat a past timestamp as expired, while the
     * function-call path accepts a row only when validuntil is null *or already in the
     * past*. A future expiry therefore kills every function call while file downloads keep
     * working -- a failure mode with no useful diagnostics. Null is the only value that
     * behaves the same way on both paths.
     *
     * @param int $serviceid External service ID.
     * @param int $userid User to authorise.
     * @return void
     */
    public static function ensure_authorised(int $serviceid, int $userid): void {
        global $DB;

        if ($userid <= 0) {
            return;
        }
        // There is no unique index on (externalserviceid, userid), so a duplicate row is
        // possible and makes core's UNION return the service twice.
        if ($DB->record_exists('external_services_users', ['externalserviceid' => $serviceid, 'userid' => $userid])) {
            return;
        }

        $DB->insert_record('external_services_users', (object)[
            'externalserviceid' => $serviceid,
            'userid' => $userid,
            'iprestriction' => null,
            'validuntil' => null,
            'timecreated' => time(),
        ]);
    }

    /**
     * Reduce the authorised-user list to exactly the given users, one row each.
     *
     * Used after the ownership cutover to withdraw the administrator's authorisation once
     * no administrator-owned token survives, and to collapse any duplicate rows.
     *
     * @param int $serviceid External service ID.
     * @param array $keepuserids User IDs that must remain authorised.
     * @return void
     */
    public static function converge_authorised(int $serviceid, array $keepuserids): void {
        global $DB;

        $keep = array_values(array_unique(array_filter(array_map('intval', $keepuserids), fn($id) => $id > 0)));

        if ($keep) {
            [$insql, $params] = $DB->get_in_or_equal($keep, SQL_PARAMS_NAMED, 'keep', false);
            $params['serviceid'] = $serviceid;
            $DB->delete_records_select(
                'external_services_users',
                "externalserviceid = :serviceid AND userid {$insql}",
                $params
            );
        } else {
            $DB->delete_records('external_services_users', ['externalserviceid' => $serviceid]);
        }

        foreach ($keep as $userid) {
            $DB->delete_records('external_services_users', [
                'externalserviceid' => $serviceid,
                'userid' => $userid,
            ]);
            self::ensure_authorised($serviceid, $userid);
        }
    }

    /**
     * Repair the service flags an administrator may have changed by hand.
     *
     * Core rewrites these from db/services.php on a version bump, but never afterwards, so
     * an administrator who re-enables file upload or unrestricts the service in the web
     * services UI would otherwise stay that way indefinitely.
     *
     * Must only be called once the service account is authorised: locking a service that
     * has no authorised users is an immediate outage.
     *
     * @return void
     */
    public static function ensure_service_flags(): void {
        global $DB;

        $service = $DB->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME]);
        if (!$service) {
            return;
        }

        $desired = ['restrictedusers' => 1, 'uploadfiles' => 0, 'downloadfiles' => 1, 'enabled' => 1];
        $changed = false;
        foreach ($desired as $field => $value) {
            if ((int)$service->{$field} !== $value) {
                $service->{$field} = $value;
                $changed = true;
            }
        }
        if (!$changed) {
            return;
        }

        $service->timemodified = time();
        $DB->update_record('external_services', $service);
    }

    /**
     * Report the first structural problem with the service identity, if any.
     *
     * The codes are deliberately fixed strings with no interpolated site data: they are
     * recorded in configuration, shown on the settings page and mailed to administrators.
     *
     * @return string|null Safe problem code, or null when everything is in order.
     */
    public static function health_problem(): ?string {
        global $DB;

        $userid = self::locate();
        if ($userid <= 0) {
            return 'service_account_missing';
        }
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0])) {
            return 'service_account_unusable';
        }

        $roleid = (int)get_config('local_corolair', 'serviceroleid');
        if ($roleid <= 0) {
            return 'service_account_role_missing';
        }
        $assigned = $DB->record_exists('role_assignments', [
            'roleid' => $roleid,
            'userid' => $userid,
            'contextid' => \context_system::instance()->id,
        ]);
        if (!$assigned) {
            return 'service_account_role_missing';
        }

        // Two cheap canaries rather than a full capability sweep, chosen because they are
        // the two whose absence stops everything: without the protocol capability no call
        // is even attempted, and without course:view every course-scoped call throws.
        $system = \context_system::instance();
        if (!has_capability('webservice/rest:use', $system, $userid)) {
            return 'service_account_capabilities_missing';
        }
        if (!has_capability('moodle/course:view', $system, $userid)) {
            return 'service_account_capabilities_missing';
        }

        $service = $DB->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME]);
        if (!$service) {
            return null;
        }
        if (!$DB->record_exists('external_services_users', ['externalserviceid' => $service->id, 'userid' => $userid])) {
            return 'service_account_not_authorised';
        }
        if ((int)$service->restrictedusers !== 1) {
            return 'service_restrictedusers_drift';
        }
        if ((int)$service->uploadfiles !== 0) {
            return 'service_uploadfiles_drift';
        }
        if ((int)$service->downloadfiles !== 1) {
            return 'service_downloadfiles_drift';
        }

        return null;
    }

    /**
     * Suspend the service account, without deleting it.
     *
     * Deliberately not delete_user(). That mangles the username irreversibly and fires
     * user_deleted into grade, enrolment and message observers for an account that has none
     * of that -- and because reinstalling this plugin is an ordinary thing to do, deletion
     * would leave a graveyard of dead accounts, one per install cycle. Suspension is both
     * reversible by ensure_user() and a stronger statement than "harmless now that its role
     * is gone": a suspended user cannot authenticate to the web service at all.
     *
     * @return void
     */
    public static function suspend(): void {
        global $CFG, $DB;

        $userid = self::locate();
        if ($userid <= 0) {
            return;
        }
        if (!$DB->record_exists('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0])) {
            return;
        }

        require_once($CFG->dirroot . '/user/lib.php');
        user_update_user((object)['id' => $userid, 'suspended' => 1], false, true);
        // Moodle 4.5 renamed kill_user_sessions() to destroy_user_sessions() and deprecated the old
        // name; we still support 4.4, where only the old name exists.
        if (method_exists('\core\session\manager', 'destroy_user_sessions')) {
            \core\session\manager::destroy_user_sessions($userid);
        } else {
            \core\session\manager::kill_user_sessions($userid);
        }
    }

    /**
     * Remove the service role, if it still exists.
     *
     * Not optional at uninstall. Core's capabilities_cleanup() only removes rows for this
     * plugin's own local/corolair capabilities, so a role left behind would keep holding
     * moodle/course:view and moodle/site:accessallgroups at system context -- worse residue
     * than a stale token.
     *
     * @return void
     */
    public static function remove_role(): void {
        global $DB;

        $roleid = (int)get_config('local_corolair', 'serviceroleid');
        $role = false;
        if ($roleid > 0) {
            $role = $DB->get_record('role', ['id' => $roleid], 'id');
        }
        if (!$role) {
            $role = $DB->get_record('role', ['shortname' => self::ROLE_SHORTNAME], 'id');
        }
        if (!$role) {
            return;
        }

        // The lookup above is required: delete_role() reads the role record to build its
        // event and fatals on a missing role.
        delete_role((int)$role->id);
    }

    /**
     * Return the external service ID, or 0 when the service is not installed.
     *
     * @return int
     */
    public static function service_id(): int {
        global $DB;

        $service = $DB->get_record('external_services', ['shortname' => self::SERVICE_SHORTNAME], 'id');
        return $service ? (int)$service->id : 0;
    }
}
