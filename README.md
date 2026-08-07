# Raison Moodle Plugin

**Version:** 1.9.3

**Last Updated:** 2026/08/07

## Overview

The Raison Moodle Plugin brings **AI Tutors** directly into Moodle. It helps trainers turn existing course content into an interactive learning experience where learners can ask questions, review key concepts, and check their understanding without leaving their course.

Each AI Tutor is created and managed by a trainer or instructional designer. The tutor uses the learning resources selected for it and can be adjusted to match the course, audience, and teaching goals. Trainers remain in control of the tutor's sources and behavior.

### Key Features

- **For Instructional Designers & Trainers:**
  - Create AI Tutors from Moodle courses without rebuilding course content.
  - Select Moodle resources and add complementary material for the tutor to use.
  - Customize each tutor's instructions, behavior, and learning purpose.
  - Share tutors with the appropriate learners and courses.
  - Review usage, common questions, and discussion trends to identify where learners may need more support.
- **For Learners:**
  - Open the AI Tutor from an enrolled Moodle course.
  - Ask questions and receive answers based on the learning material chosen by the trainer.
  - Review a topic through a guided conversation at their own pace.
  - Pause a discussion and return to it later.
  - Use self-assessment activities prepared and validated by the trainer.

---

## Installation

The plugin requires Moodle 3.8 or later and must be installed by a Moodle site administrator. Moodle 4.4 or later is recommended if you plan to use the Raison LTI integration, but it is not required for LTI or for the plugin's other features.

1. Install the latest version from the Moodle Plugins directory.
2. Go to **Site administration > Plugins > Local plugins > Raison Local Plugin** and select **Open Corolair setup**.
3. Review the integration disclosure. It explains what the plugin can access, why that access is needed, and how information is used.
4. If Moodle web services or REST are not already enabled, approve the required configuration changes. The plugin does not make these site-wide changes without administrator approval.
5. Start the Raison registration. Once registration is complete, a free-trial account is created for the administrator who began the setup.
6. Assign the _Raison Manager_ role to the trainers and instructional designers who should create and manage AI Tutors. A trial account is created automatically for each assigned trainer.

Only an authorized Moodle administrator can activate the integration. Existing Moodle web-service settings are preserved, and the setup page clearly identifies any change that requires approval.

### Continuing after the free trial

If your organization chooses to continue using Raison after the free trial, continued service will require formal agreements between Raison and your organization. These agreements will define each party's responsibilities and ensure alignment on data protection, privacy, security, and applicable regulatory requirements.

### Privacy and security

During setup, administrators can review the plugin's exact access permissions, the Moodle functions it uses, and the purpose of each type of data involved. The integration uses restricted, short-lived access credentials that are renewed automatically. Trainer sign-in is limited to approved, secure Raison destinations.

#### Credential replacement after an upgrade

When upgrading an existing installation that uses legacy credentials, the plugin schedules their replacement as a Moodle ad-hoc task instead of performing it inside the upgrade request. Raison must verify the replacement token by calling back to Moodle, but Moodle web services may not be available or reliably reachable while an upgrade is in progress. Deferring this network-dependent step allows Moodle to finish the upgrade before the plugin creates and verifies the replacement credentials.

The compatible Raison migration endpoint must be deployed before this plugin upgrade so it can replace credentials for installations that already use an active, expiring Moodle token.

Moodle cron runs the queued task after the upgrade. The task replaces the legacy API key and web-service token, verifies the new credentials, and retries safely if the remote verification cannot be completed. The legacy credentials remain active until the replacement has been verified successfully, so administrators should ensure that cron is running and that Moodle can communicate with Raison after upgrading.

#### Disabling token rotation

By default the web-service token expires after 15 days and is replaced before it expires. Deployments that cannot rely on that can turn it off with the **Disable web-service token rotation** setting. The token then does not expire, and the plugin stops replacing it.

**Why this option exists.** A rotating credential is the better design, but it assumes someone is reachable when it fails. In practice that assumption often does not hold:

- The registered Moodle administrator address is frequently a placeholder such as `admin@example.com`, or a shared mailbox nobody reads. Every safeguard the rotation lifecycle has — the expiry warning, the failure notice on the settings page — is a message to an address that goes nowhere.
- Many installations are maintained by an external integrator rather than by the institution. When rotation stops working, the fix goes into a ticket queue that can take weeks and is often billed, so a credential that expires on a fixed schedule turns a minor cron problem into a paid outage.
- Cron on shared or tightly managed hosting is not always reliable, and outbound HTTPS to Raison is sometimes restricted after the fact, without the plugin being told.

In each of those cases the automated replacement fails silently and the integration simply stops working, with no one positioned to notice or act. A long-lived credential is a genuine reduction in security posture, and it is off by default — but for these deployments it is the difference between an integration that keeps working and one that breaks with nobody watching. The option is a deliberate concession to how these sites are actually run.

The setting converges in both directions. Enabling it replaces the current token with a non-expiring one; disabling it again replaces that token with a normal 15-day one. Either change is applied by the scheduled task, so it needs cron and a successful call to Raison, and the previous token stays usable for up to 7 more days so requests already in flight are not interrupted. The plugin settings page reports whether the change has been applied yet.

The choice is also offered on the setup consent page, before any token exists. Choosing there is preferable: the first token is created with the right lifetime, so the site avoids the immediate replacement that changing the setting afterwards causes. The same value can be preset before setup through `$CFG->forced_plugin_settings`, in which case it is fixed in the server configuration and the setup page reports it as such rather than offering a choice that could not take effect.

Two consequences are worth weighing before enabling it. Rotation is the only thing that makes Raison periodically re-verify that this site still grants the functions the integration needs, so misconfiguration is detected later; the plugin compensates with its own periodic check, but that check is weaker. And the credential is long-lived rather than short-lived, which is a deliberate reduction in the security posture the default provides.

A compatible Raison deployment is required, and must be deployed before this plugin upgrade. Against an older Raison deployment the change is simply not applied: the current token stays active and the plugin settings page reports the failure, so nothing breaks, but the setting has no effect until Raison is updated. Note that the two sides record "never expires" differently — Moodle stores a far-future expiry on the token record, while Raison stores no expiry at all.

#### Local consent and accountability records

The plugin stores the following records in Moodle's `config_plugins` table under the `local_corolair` component:

- `setupconsentedby`: the Moodle user ID of the administrator who authorized activation of the integration.
- `setupconsentedat`: the date and time when activation was authorized.
- `setupdisclosureacknowledgedby`: the Moodle user ID of the administrator who acknowledged the integration disclosure.
- `setupdisclosureacknowledgedat`: the date and time when the disclosure was acknowledged.

These records apply only to administrators who activate the integration or acknowledge its disclosure. They are used to demonstrate authorization and maintain operational accountability. They remain within the Moodle installation and are separate from data processed by the external Raison service.

The records are available for discovery and export through Moodle's Privacy API. They are not automatically removed by an individual erasure request because they identify the accountable owner of the active integration. The Moodle operator is responsible for determining and documenting the applicable lawful basis and retention requirements. Access must be limited to authorized Moodle administrators and other personnel who require it for privacy, audit, or operational purposes.

Uninstalling the plugin removes these records with the rest of the plugin configuration.

For questions about data processing, retention, deletion, privacy, or security, contact contact@raison.is.

### Uninstallation and remote deletion

Uninstalling the plugin revokes the Raison Moodle web-service access and removes the local service, token, role, API key, and plugin configuration. Before local cleanup, the plugin makes up to three synchronous attempts to deregister the organization from Raison and requires an explicit `disconnected` response.

Revoking the Moodle token prevents future access to the Moodle instance; it does not by itself prove deletion of data previously transferred to Raison. If remote deregistration cannot be confirmed, Moodle still completes local cleanup and warns the administrator to contact contact@raison.is under the applicable service or data processing agreement. The request must include the Moodle site URL so Raison can identify the organization and complete the deletion process.

---

## Access

### For Trainers & Instructional Designers

- **From the Moodle homepage:** Go to **Plus > Raison** to open the Creator tools.
- **From a course:** Go to **Plus > Raison AI Assistant** to create or manage an AI Tutor for that course.

In the Creator platform, trainers can:

- Create, share, and monitor AI Tutors.
- Choose and update the learning sources used by a tutor.
- Adjust tutor instructions and behavior.
- Review discussions and identify recurring learner questions.

---

### For Learners

- Open the Raison AI Tutor from a Moodle course where it has been made available by the trainer.
- Start a conversation, ask questions about course content, and return to previous discussions later.
- Move between guided discussion and self-assessment when those options are enabled by the trainer.

---

## Roadmap

### Version 2.0: Proactive AI Tutor

- **Mobile Compatibility:** Seamlessly continue the AI Assistant experience on mobile devices.
- **Expanded Learning Capabilities:** Beyond Q&A, the AI Tutor will provide a complete learning experience, enabling learners to explore topics from scratch in new and engaging ways, distinct from the current Moodle course structure.
- **Personalized Learning Paths:** Tailor learning journeys to individual skill levels and performance, ensuring optimal progress for each learner.
- **Microlearning Support:** Break down courses and resources into bite-sized lessons that can be completed in under 5 minutes for greater focus and retention.
- **Multimedia Integration:** Offer lessons in multimedia formats, including videos, audio clips, and interactive visuals, to enhance comprehension and engagement.
- **Voice Search:** Enable learners to interact with the AI Tutor using voice commands for quick and intuitive navigation.

---

## Local development

`make` is the entry point; run `make` on its own to list every target. There are two tiers.

**Tier 1 — fast checks, no database or container.** One-time setup:

```bash
brew install php@8.2 composer
composer install
```

Then, in under a second each:

```bash
make lint    # php -l across the plugin
make cs      # Moodle Code Checker (the `moodle` phpcs standard)
make fix     # auto-fix what the checker can
make check   # lint + cs
```

**Tier 2 — full parity with GitHub Actions, including PHPUnit.** Requires Docker.

```bash
make up setup    # start MariaDB, install Moodle and the plugin (several minutes, once)
make ci          # every step CI runs, plus phpunit
make phpunit     # just the tests
make shell       # a shell inside the CI container
```

Individual steps are available too: `make phplint phpcs phpdoc validate savepoints mustache`.

The CI matrix covers two combinations: PHP 8.2 with Moodle 5.1 (the default) and PHP 8.1 with Moodle 4.5. Set `PHP_VERSION` alone and the matching Moodle branch follows:

```bash
PHP_VERSION=8.1 make setup ci    # the older leg
make setup ci                    # back to the default
```

The two are paired on purpose — Moodle 5.1 requires PHP 8.2, so an 8.1 image with a 5.1 checkout fails deep inside `composer install`. `make up` rebuilds the image whenever `PHP_VERSION` changes, so the running PHP cannot drift away from the installed Moodle.

Notes:

- `moodle-plugin-ci` copies the plugin into its Moodle tree rather than symlinking it, so every Tier 2 target runs `make sync` first to push your edits across. Editing files while a long run is in flight will not affect it.
- `make setup` wipes and reinstalls both the Moodle checkout and the test database, so it is safe to re-run when switching branches or PHP versions.
- `make down` stops the containers; the Moodle checkout and the database both survive, so `make ci` works again straight away without re-running `setup`. `make clean` deletes them and does require a fresh `setup`.
- Every Tier 2 target starts the containers itself, so `make ci` works from cold. If the Moodle install is missing or incomplete, they stop with one clear message rather than reporting passes against a half-installed tree.

**Packaging.** `make package` builds `local_corolair.zip` from the committed tree using `git archive`, honouring the `export-ignore` rules in `.gitattributes` so development files stay out of the release. It refuses to run against a dirty working tree, because `git archive` packages `HEAD` and the zip would otherwise not match your files.

---

## Contributing

Suggestions and feedback are welcome. You can submit feature requests or report issues through the [Corolair Plugin GitHub repository](https://github.com/corolair/moodle-local_corolair).

---

## Support

For installation help, product questions, or technical support, contact the Raison team at contact@raison.is.

---

_Copyright © 2025 Raison. All rights reserved._
