---
title: Data sources
description: Every external source, its license, cost, and refresh cadence
weight: 9
---

# Data sources

Free-core signals use only sources with clean licenses and no paid gate. Paid /
keyed providers are strictly opt-in. Nothing here sends your users' data to a third
party except where you explicitly enable it (StopForumSpam, and any paid driver you
add).

## Shipped (free-core)

| Source | Used by | License | Cost | Refresh |
|--------|---------|---------|------|---------|
| [stamparm/ipsum](https://github.com/stamparm/ipsum) | `ip.reputation` | **Unlicense** (public domain) | Free, no key | `risk:refresh-ipsum` — daily |
| [Tor bulk exit list](https://check.torproject.org/torbulkexitlist) | `ip.tor_exit` | Public | Free | `risk:refresh-tor` — hourly |
| Bundled disposable-domain list | `email.disposable` | starter subset; full list from [amieiro](https://github.com/amieiro/disposable-email-domains) is **MIT** | Free | manual / your schedule |
| System DNS (MX/A) | `email.no_mx` | — | Free | per-request (cache in your resolver) |
| Your cache (Redis) | `velocity` | — | — | live |

The honeypot and user-agent signals use **no external data** at all.

## Opt-in (shipped, enable in config)

| Source | Used by | License | Cost / limits |
|--------|---------|---------|---------------|
| [StopForumSpam API](https://www.stopforumspam.com/) | `ip.stopforumspam` | Data is **CC BY-NC** — fine for a self-hoster protecting their own site, not for resale | Free; ~20k lookups/day. Cached per IP, short timeout, fail-open. For high volume prefer the downloadable dump behind a custom `IpReputation`. |

## Opt-in (bring your own key / implementation)

Add these as a custom `Signal` or by binding `IpReputation` / `DisposableDomains`
(see [Extending](../extension-points/_index.md)):

| Source | License / cost | Notes |
|--------|----------------|-------|
| [AbuseIPDB](https://www.abuseipdb.com/) | Free 1k/day, paid tiers; attribution + key | `abuseConfidenceScore` 0–100 maps straight to points |
| [Spamhaus DNSBL](https://www.spamhaus.org/) | Free for **low-volume/non-commercial** via public mirrors; DQS (paid) otherwise | XBL/PBL zones; a cheap DNS query |
| [Project Honey Pot http:BL](https://www.projecthoneypot.org/) | Free with key | web-traffic oriented threat score |
| [IP2Location LITE](https://lite.ip2location.com/) | **CC BY-SA** (attribution) | country/ASN — high-risk country + datacenter detection. **Preferred over MaxMind** (friendlier license, no account gate) |
| [MaxMind GeoLite2](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data/) | Restrictive EULA (no redistribution, 30-day updates), account + key | do **not** bundle; support as a driver only |
| [IPQualityScore](https://www.ipqualityscore.com/) / [IPHub](https://iphub.info/) | free tiers + paid | residential-proxy / VPN detection |
| [HIBP Pwned Passwords](https://haveibeenpwned.com/API/v3) | Free, no key, k-anonymity | breached-password check at registration/change |

## License obligations to honor

- **IP2Location LITE** and the **disposable-domain lists**: attribution required.
- **StopForumSpam**: non-commercial data — self-host use only, don't resell.
- **MaxMind**: don't redistribute the DB; auto-update within 30 days if you use it.
- **ipsum** (Unlicense) and **HIBP passwords**: no obligations — the cleanest to
  ship on by default.

Keep this page current when you add or swap a source — it's the honest record of
what your deployment talks to.
