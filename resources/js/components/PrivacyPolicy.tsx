export function PrivacyPolicy() {
  return (
    <div className="min-h-screen hero-gradient py-12 px-6">
      <div className="max-w-4xl mx-auto bg-white rounded-lg shadow-xl p-8 md:p-12">
        <h1 className="text-4xl font-bold text-foreground mb-8">Privacy Policy</h1>
        <div className="space-y-6 text-muted-foreground">
          <p className="text-sm text-muted-foreground">
            <strong>Effective Date:</strong> {new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}
          </p>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">1. Introduction</h2>
            <p>
              Welcome to AppliFlow ("we," "our," or "us"). We are committed to protecting your privacy and personal information.
              This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our job
              application automation platform.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">2. Information We Collect</h2>
            <h3 className="text-xl font-medium text-foreground mb-2">2.1 Information You Provide</h3>
            <ul className="list-disc list-inside space-y-2 ml-4">
              <li><strong>Account Information:</strong> Name, email address, password, and other registration details</li>
              <li><strong>Profile Information:</strong> Resume/CV, work experience, skills, education, and career preferences</li>
              <li><strong>Application Data:</strong> Job applications, cover letters, and related communication</li>
              <li><strong>Communication:</strong> Messages sent through our platform or to our support team</li>
            </ul>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">2.2 Automatically Collected Information</h3>
            <ul className="list-disc list-inside space-y-2 ml-4">
              <li><strong>Usage Data:</strong> Pages visited, features used, time spent on the platform</li>
              <li><strong>Device Information:</strong> IP address, browser type, operating system, device identifiers</li>
              <li><strong>Cookies and Tracking:</strong> We use cookies and similar technologies to enhance your experience</li>
            </ul>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">2.3 Third-Party Information</h3>
            <ul className="list-disc list-inside space-y-2 ml-4">
              <li><strong>OAuth Providers:</strong> Information from Google OAuth when you connect your Gmail account</li>
              <li><strong>Job Boards:</strong> Publicly available job listings and company information</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">3. How We Use Your Information</h2>
            <p className="mb-2">We use your information to:</p>
            <ul className="list-disc list-inside space-y-2 ml-4">
              <li>Provide, maintain, and improve our job application automation services</li>
              <li>Match you with relevant job opportunities based on your profile</li>
              <li>Submit job applications on your behalf with your explicit consent</li>
              <li>Send you notifications about job matches and application status</li>
              <li>Communicate with you about our services, updates, and promotional offers</li>
              <li>Analyze usage patterns to enhance platform functionality</li>
              <li>Detect, prevent, and address technical issues and fraudulent activity</li>
              <li>Comply with legal obligations and enforce our terms of service</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">4. Information Sharing and Disclosure</h2>
            <h3 className="text-xl font-medium text-foreground mb-2">4.1 With Your Consent</h3>
            <p>We share your information with prospective employers when you apply to jobs through our platform.</p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">4.2 Service Providers</h3>
            <p>
              We may share information with third-party service providers who assist us in operating our platform,
              including cloud hosting, email delivery, analytics, and customer support services.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">4.3 Legal Requirements</h3>
            <p>
              We may disclose your information if required by law, court order, or governmental regulation, or if we
              believe disclosure is necessary to protect our rights, your safety, or the safety of others.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">4.4 Business Transfers</h3>
            <p>
              In the event of a merger, acquisition, or sale of assets, your information may be transferred to the
              acquiring entity.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">5. Data Security</h2>
            <p>
              We implement appropriate technical and organizational measures to protect your personal information against
              unauthorized access, alteration, disclosure, or destruction. These measures include:
            </p>
            <ul className="list-disc list-inside space-y-2 ml-4">
              <li>Encryption of data in transit and at rest</li>
              <li>Regular security assessments and updates</li>
              <li>Access controls and authentication requirements</li>
              <li>Employee training on data protection practices</li>
            </ul>
            <p className="mt-2">
              However, no method of transmission over the internet or electronic storage is 100% secure. While we strive
              to protect your information, we cannot guarantee absolute security.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">6. Your Rights and Choices</h2>
            <p className="mb-2">You have the right to:</p>
            <ul className="list-disc list-inside space-y-2 ml-4">
              <li><strong>Access:</strong> Request a copy of the personal information we hold about you</li>
              <li><strong>Correction:</strong> Update or correct inaccurate information</li>
              <li><strong>Deletion:</strong> Request deletion of your personal information, subject to legal obligations</li>
              <li><strong>Opt-Out:</strong> Unsubscribe from marketing communications at any time</li>
              <li><strong>Data Portability:</strong> Request a copy of your data in a machine-readable format</li>
              <li><strong>Withdraw Consent:</strong> Revoke consent for data processing where applicable</li>
            </ul>
            <p className="mt-2">
              To exercise these rights, please contact us using the information provided below.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">7. Data Retention</h2>
            <p>
              We retain your personal information for as long as necessary to fulfill the purposes outlined in this Privacy
              Policy, unless a longer retention period is required or permitted by law. When we no longer need your
              information, we will securely delete or anonymize it.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">8. Cookies and Tracking Technologies</h2>
            <p>
              We use cookies, web beacons, and similar technologies to collect information about your browsing activities
              and preferences. You can control cookie settings through your browser preferences, but disabling cookies may
              limit functionality of our platform.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">9. Third-Party Links</h2>
            <p>
              Our platform may contain links to third-party websites and services. We are not responsible for the privacy
              practices of these third parties. We encourage you to review their privacy policies before providing any
              personal information.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">10. Children's Privacy</h2>
            <p>
              Our services are not intended for individuals under the age of 18. We do not knowingly collect personal
              information from children. If we become aware that we have collected information from a child, we will
              take steps to delete it promptly.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">11. International Data Transfers</h2>
            <p>
              Your information may be transferred to and processed in countries other than your country of residence.
              These countries may have different data protection laws. By using our services, you consent to such transfers.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">12. Changes to This Privacy Policy</h2>
            <p>
              We may update this Privacy Policy from time to time. We will notify you of any material changes by posting
              the new policy on our platform and updating the effective date. Your continued use of our services after
              such changes constitutes acceptance of the updated policy.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">13. Contact Us</h2>
            <p className="mb-2">
              If you have any questions, concerns, or requests regarding this Privacy Policy or our data practices,
              please contact us at:
            </p>
            <div className="ml-4">
              <p><strong>Email:</strong> privacy@appliflow.com</p>
              <p><strong>Address:</strong> AppliFlow HQ</p>
            </div>
          </section>

          <div className="pt-8 border-t border-border mt-8">
            <p className="text-sm">
              This Privacy Policy was last updated on {new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
