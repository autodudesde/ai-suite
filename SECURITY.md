# Security Policy

AI Suite (`autodudes/ai-suite`, extension key `ai_suite`) is developed and maintained by
AutoDudes, Germany.

## Reporting a vulnerability

Please report a suspected vulnerability privately by email to **security@autodudes.de**. Do not
open a public issue, pull request or forum post for it.

A useful report names:

- the affected package and version, and the TYPO3 and PHP version
- the steps to reproduce, ideally with a minimal example
- the impact you expect, for example data exposure or privilege escalation

What happens next:

- We confirm receipt within one working day.
- We send you our initial assessment, including a CVSS severity, within five working days.
- We keep you informed until a fix is released and agree the publication date with you. Our
  target is no more than 90 days after your report.
- Fixes are published as security releases of every affected major listed below.

A vulnerability in the TYPO3 core or in another extension goes to the TYPO3 Security Team at
security@typo3.org instead.

The full disclosure policy, including the rules for testing, is published at
<https://www.autodudes.de/en/security> (German: <https://www.autodudes.de/sicherheit>). The contact
is also listed in <https://www.autodudes.de/.well-known/security.txt>.

## Actively exploited vulnerabilities

If a vulnerability in AI Suite is actively exploited, or an incident affects its security, we report
it through the ENISA Single Reporting Platform within the deadlines of the EU Cyber Resilience Act:
an early warning within 24 hours and a notification within 72 hours of becoming aware of it.

Installations learn of it in the TYPO3 backend: admins see the advisory and the first fixed version
in **System → Reports** (*AI Suite security updates*) and in the system information menu of the
toolbar. For a critical vulnerability the AI Suite Server can refuse requests from affected versions
until the update is installed. Advisories are published as
[GitHub security advisories](https://github.com/autodudesde/ai-suite/security/advisories) and in the
**Security** section of [CHANGELOG.md](CHANGELOG.md).

## Supported versions

Every major receives security updates for five years from its first release. New features are
developed for the newest major only. Security updates are free of charge.

| AI Suite | TYPO3 | First release | Security updates until |
|---|---|---|---|
| 14.x | 14 | April 2026 | April 2031 |
| 13.x | 13 | November 2024 | November 2029 |
| 12.x | 12 | April 2024 | April 2029 |
| 11.x and older | 11 and older | | ended |

Within a major, only the latest release is supported: install every update of your major.

Every published release stays available where it was published (TER, Packagist, GitHub) for at
least ten years after its release.

## Related packages

AI Suite MCP, ChEddi and FrEddi are separate packages with their own security policy in their
repositories. Every AI request of AI Suite and these packages passes the AI Suite Server operated
by AutoDudes; vulnerabilities in that service are reported through the same address.

## TYPO3 Core and third-party packages

AI Suite runs on the TYPO3 Core and on further packages that Composer resolves during installation.
Vulnerabilities in the TYPO3 Core are fixed by the TYPO3 Security Team and announced as [TYPO3
security advisories](https://typo3.org/help/security-advisories); vulnerabilities in other packages
are fixed by their maintainers. Keeping TYPO3 and these packages on the latest patch level is the
responsibility of whoever operates the installation.

The minimum versions in `composer.json` are the lowest versions AI Suite works with. They are
technical requirements, not a security recommendation, and they do not exclude versions with known
vulnerabilities.

TYPO3 12 receives security fixes from 12.4.46 on only through TYPO3 Extended Long Term Support
(ELTS); the last public release is 12.4.45 (April 2026). Without ELTS, an installation runs on a
TYPO3 Core with known vulnerabilities, whatever version of AI Suite it uses.

Every release of AI Suite is checked against the newest dependency versions available for each
supported TYPO3 major. If a vulnerability in a dependency can be exploited through AI Suite, we
publish our own advisory and raise the minimum version where that helps. Third-party code we ship
ourselves (the bundled JavaScript libraries) is maintained by us like our own code.
