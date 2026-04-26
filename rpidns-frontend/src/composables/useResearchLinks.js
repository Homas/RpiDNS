/*
 * (c) Vadim Pavlov 2020 - 2026
 * Research links composable for RpiDNS
 * Provides shared research link definitions for context menus and ResearchLinks component
 */

/**
 * Research link definitions for external threat intelligence services.
 * Each entry defines a service name, URL template with {domain} placeholder, and optional icon.
 * DuckDuckGo and Google use quoted search (%22 = URL-encoded double quote).
 */
export const RESEARCH_LINKS = [
  {
    name: 'DuckDuckGo',
    urlTemplate: 'https://duckduckgo.com/?q=%22{domain}%22',
    icon: null
  },
  {
    name: 'Google',
    urlTemplate: 'https://www.google.com/search?q=%22{domain}%22',
    icon: null
  },
  {
    name: 'VirusTotal',
    urlTemplate: 'https://www.virustotal.com/gui/search/{domain}',
    icon: null
  },
  {
    name: 'DomainTools Whois',
    urlTemplate: 'http://whois.domaintools.com/{domain}',
    icon: null
  },
  {
    name: 'Robtex',
    urlTemplate: 'https://www.robtex.com/dns-lookup/{domain}',
    icon: null
  },
  {
    name: 'ThreatMiner',
    urlTemplate: 'https://www.threatminer.org/domain.php?q={domain}',
    icon: null
  }
]

/**
 * Generate research URLs for a given domain.
 * Replaces the {domain} placeholder in each template with the actual domain string.
 * @param {string} domain - The domain name to generate research URLs for
 * @returns {Array<{name: string, url: string, icon: string|null}>} Array of research link objects
 */
export function getResearchUrls(domain) {
  return RESEARCH_LINKS.map(link => ({
    name: link.name,
    url: link.urlTemplate.replace('{domain}', domain),
    icon: link.icon
  }))
}
