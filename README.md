# Raison Moodle Plugin

**Version:** 1.9.0

**Last Updated:** 2026/07/31

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

## Contributing

Suggestions and feedback are welcome. You can submit feature requests or report issues through the [Corolair Plugin GitHub repository](https://github.com/corolair/moodle-local_corolair).

---

## Support

For installation help, product questions, or technical support, contact the Raison team at contact@raison.is.

---

_Copyright © 2025 Raison. All rights reserved._
