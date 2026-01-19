<!-- (c) Vadim Pavlov 2020 - 2026 -->
<template>
  <div class="donate-wrapper h-100 overflow-auto p-3">
    <BCard>
      <template #header>
        <span class="bold"><i class="fas fa-money-bill-wave text-success"></i>&nbsp;&nbsp;Support ioc2rpz Project</span>
      </template>

      <div class="donate-content">
        <section class="mb-4">
          <h3><i class="fas fa-heart text-danger"></i> Why Donate?</h3>
          <p>
            <strong>ioc2rpz</strong> is a free, open-source project that provides DNS Firewall (Response Policy Zone) 
            feeds to protect networks from malware, phishing, advertising, and other threats. The project is maintained 
            by volunteers and relies on community support to cover infrastructure costs and continue development.
          </p>
          <p>
            Your donation helps to:
          </p>
          <ul>
            <li><i class="fas fa-server text-primary"></i> Maintain servers and infrastructure that process threat intelligence feeds</li>
            <li><i class="fas fa-shield-alt text-success"></i> Keep RPZ feeds updated with the latest threat data</li>
            <li><i class="fas fa-code text-info"></i> Support ongoing development and improvements</li>
            <li><i class="fas fa-users text-warning"></i> Provide free DNS security to the community</li>
          </ul>
        </section>

        <section class="mb-4">
          <h3><i class="fas fa-donate text-success"></i> How to Donate</h3>
          <p>
            If you find ioc2rpz and RpiDNS useful, please consider supporting the project. Every contribution, 
            no matter how small, helps keep the service running and free for everyone.
          </p>
          
          <div class="donate-options mt-4">
            <div class="donate-option mb-3">
              <h5><i class="fa-brands fa-github"></i> GitHub Sponsors</h5>
              <p>Make a one-time or recurring donation via GitHub Sponsors:</p>
              <a href="https://github.com/sponsors/Homas" 
                 target="_blank" 
                 class="btn btn-secondary">
                <i class="fa-brands fa-github"></i> Donate with GitHub
              </a>
            </div>

            <div class="donate-option mb-3">
              <h5><i class="fab fa-paypal text-primary"></i> PayPal</h5>
              <p>Make a one-time donation via PayPal:</p>
              <a href="https://paypal.me/ioc2rpz" 
                 target="_blank" 
                 class="btn btn-primary">
                <i class="fab fa-paypal"></i> Donate with PayPal
              </a>
            </div>

            <div class="donate-option mb-3">
              <h5><i class="fa-solid fa-dollar-sign"></i> Zelle</h5>
              <p>Make a one-time donation via Zelle:</p>
              <p>Our email: zelle [at] ioc2rpz [.] net (please replace [at] and [.], one time donation)</p>
              <a href="https://www.zellepay.com/" 
                 target="_blank" 
                 class="btn btn-info">
                 <i class="fa-solid fa-dollar-sign"></i> Donate with Zelle
              </a>
            </div>

            <div class="donate-option mb-3">
              <h5><i class="fab fa-bitcoin text-warning"></i> Cryptocurrency</h5>
              <p>Support the project with cryptocurrency:</p>
              <div class="crypto-addresses">
                <div class="crypto-item mb-2">
                  <strong>Bitcoin (BTC):</strong>
                  <code class="ms-2">3BKgynCSo4AsF6Z6x9zvssufHNYJnchH1G</code>
                </div>
                <div class="crypto-item mb-2">
                  <strong>Ethereum (ERC-20):</strong>
                  <code class="ms-2">0xFB69352b70744BE15ca97797D0BB83eb9b659b23</code>
                </div>
                <div class="crypto-item mb-2">
                  <strong>USDT (ERC-20):</strong>
                  <code class="ms-2">0xf46a4516fCbaFEA64702C8924B7112E25a8F698f</code>
                </div>
                <div class="crypto-item mb-2">
                  <strong>USDC (ERC-20):</strong>
                  <code class="ms-2">0x512001E0560Dc82B00f98c940bBEC2Fc565C292d</code>
                </div>
              </div>
              <p class="text-muted small mt-2">
                <i class="fas fa-info-circle"></i> Visit 
                <a href="https://ioc2rpz.net" target="_blank">ioc2rpz.net</a> 
                for the most up-to-date wallet addresses.
              </p>
            </div>
          </div>
        </section>

        <section class="mb-4">
          <h3><i class="fas fa-hands-helping text-info"></i> Other Ways to Help</h3>
          <p>If you can't donate financially, there are other ways to support the project:</p>
          <ul>
            <li><i class="fas fa-star text-warning"></i> <strong>Star the project</strong> on 
              <a href="https://github.com/Homas/ioc2rpz" target="_blank">GitHub</a></li>
            <li><i class="fas fa-bug text-danger"></i> <strong>Report bugs</strong> and suggest improvements</li>
            <li><i class="fas fa-share-alt text-primary"></i> <strong>Spread the word</strong> - tell others about ioc2rpz</li>
            <li><i class="fas fa-code-branch text-success"></i> <strong>Contribute code</strong> - submit pull requests</li>
            <li><i class="fas fa-book text-info"></i> <strong>Improve documentation</strong> - help others get started</li>
          </ul>
        </section>

        <section class="mb-4">
          <h3><i class="fas fa-link text-secondary"></i> Project Links</h3>
          <ul class="list-unstyled">
            <li class="mb-2">
              <i class="fas fa-globe text-primary"></i>
              <a href="https://ioc2rpz.net" target="_blank" class="ms-2">ioc2rpz.net</a> - Official website
            </li>
            <li class="mb-2">
              <i class="fab fa-github text-dark"></i>
              <a href="https://github.com/Homas/ioc2rpz" target="_blank" class="ms-2">GitHub Repository</a> - Source code
            </li>
            <li class="mb-2">
              <i class="fas fa-book text-info"></i>
              <a href="https://github.com/Homas/ioc2rpz/wiki" target="_blank" class="ms-2">Documentation</a> - Wiki and guides
            </li>
          </ul>
        </section>

        <div class="thank-you-message text-center p-4 bg-light rounded">
          <h4><i class="fas fa-heart text-danger"></i> Thank You!</h4>
          <p class="mb-0">
            Your support helps keep the internet safer for everyone. Thank you for using and supporting ioc2rpz!
          </p>
        </div>
      </div>
    </BCard>
  </div>
</template>

<script>
export default {
  name: 'DonateContent'
}
</script>

<style scoped>
.donate-wrapper {
  font-size: 0.95rem;
}

.donate-content h3 {
  color: #343a40;
  font-size: 1.3rem;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid #e9ecef;
}

.donate-content h5 {
  color: #495057;
  font-size: 1.1rem;
}

.donate-option {
  padding: 1rem;
  background-color: #f8f9fa;
  border-radius: 0.5rem;
  border-left: 4px solid #28a745;
}

.crypto-addresses code {
  background-color: #e9ecef;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.85rem;
  word-break: break-all;
}

.thank-you-message {
  border: 2px dashed #28a745;
}

.donate-content ul {
  margin-bottom: 1rem;
}

.donate-content li {
  margin-bottom: 0.5rem;
}

/* .donate-content a {
  color: #007bff;
}
 */

.donate-content a:hover {
  color: #0056b3;
  text-decoration: underline;
}
</style>
