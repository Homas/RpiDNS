<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <span>
    - <a target="_blank" :href="'https://duckduckgo.com/?q=%22' + domain + '%22'">DuckDuckGo</a><br>
    - <a target="_blank" :href="'https://www.google.com/search?q=%22' + domain + '%22'">Google</a><br>
    - <a target="_blank" :href="'https://www.virustotal.com/gui/search/' + domain">VirusTotal</a><br>
    - <a target="_blank" :href="'http://whois.domaintools.com/' + domain">DomainTools Whois</a><br>
    - <a target="_blank" :href="'https://www.robtex.com/dns-lookup/' + domain">Robtex</a><br>
    - <a target="_blank" :href="'https://www.threatminer.org/domain.php?q=' + domain">ThreatMiner</a>
  </span>
</template>

<script>
export default {
  name: 'ResearchLinks',
  props: {
    domain: {
      type: String,
      required: true
    }
  }
}
</script>
