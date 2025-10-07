export function TermsOfService() {
  return (
    <div className="min-h-screen hero-gradient py-12 px-6">
      <div className="max-w-4xl mx-auto bg-white rounded-lg shadow-xl p-8 md:p-12">
        <h1 className="text-4xl font-bold text-foreground mb-8">Terms of Service</h1>
        <div className="space-y-6 text-muted-foreground">
          <p className="text-sm text-muted-foreground">
            <strong>Effective Date:</strong> {new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}
          </p>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">1. Acceptance of Terms</h2>
            <p>
              Welcome to AppliFlow. By accessing or using our job application automation platform ("Service"), you agree
              to be bound by these Terms of Service ("Terms"). If you do not agree to these Terms, please do not use our
              Service.
            </p>
            <p className="mt-2">
              These Terms constitute a legally binding agreement between you ("User," "you," or "your") and AppliFlow
              ("Company," "we," "us," or "our").
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">2. Description of Service</h2>
            <p>
              AppliFlow provides an automated job application platform that helps users:
            </p>
            <ul className="list-disc list-inside space-y-2 ml-4">
              <li>Match with relevant job opportunities based on their profile and preferences</li>
              <li>Automatically submit job applications to prospective employers</li>
              <li>Track application status and manage the job search process</li>
              <li>Connect with employers and receive job notifications</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">3. User Accounts and Registration</h2>
            <h3 className="text-xl font-medium text-foreground mb-2">3.1 Account Creation</h3>
            <p>
              To use our Service, you must create an account by providing accurate, complete, and current information.
              You are responsible for maintaining the confidentiality of your account credentials.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">3.2 Account Responsibility</h3>
            <p>
              You are solely responsible for all activities that occur under your account. You must notify us immediately
              of any unauthorized access or security breach.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">3.3 Eligibility</h3>
            <p>
              You must be at least 18 years old and legally able to enter into contracts to use our Service. By using
              AppliFlow, you represent and warrant that you meet these requirements.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">4. User Conduct and Responsibilities</h2>
            <h3 className="text-xl font-medium text-foreground mb-2">4.1 Permitted Use</h3>
            <p>You agree to use our Service only for lawful purposes and in accordance with these Terms.</p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">4.2 Prohibited Activities</h3>
            <p className="mb-2">You agree NOT to:</p>
            <ul className="list-disc list-inside space-y-2 ml-4">
              <li>Provide false, misleading, or inaccurate information in your profile or applications</li>
              <li>Use the Service to spam, harass, or send unsolicited communications</li>
              <li>Attempt to gain unauthorized access to our systems or other user accounts</li>
              <li>Interfere with or disrupt the Service or servers</li>
              <li>Use automated scripts or bots to access the Service without authorization</li>
              <li>Reverse engineer, decompile, or disassemble any part of the Service</li>
              <li>Violate any applicable laws, regulations, or third-party rights</li>
              <li>Use the Service to submit applications to positions you are not qualified for or interested in</li>
            </ul>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">4.3 Content Accuracy</h3>
            <p>
              You are responsible for ensuring that all information in your resume, profile, and applications is accurate,
              truthful, and up-to-date. We are not responsible for any consequences resulting from inaccurate information.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">5. Application Submission and Authorization</h2>
            <h3 className="text-xl font-medium text-foreground mb-2">5.1 Authorization</h3>
            <p>
              By using our automated application service, you explicitly authorize AppliFlow to submit job applications
              on your behalf using the information you provide.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">5.2 No Guarantee</h3>
            <p>
              We do not guarantee that your applications will be successfully submitted, received by employers, or result
              in interviews or job offers. Application success depends on many factors beyond our control.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">5.3 Employer Decisions</h3>
            <p>
              All hiring decisions are made solely by employers. We are not responsible for employer responses, interview
              invitations, or job offers (or lack thereof).
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">6. Intellectual Property Rights</h2>
            <h3 className="text-xl font-medium text-foreground mb-2">6.1 Company Property</h3>
            <p>
              The Service, including all content, features, functionality, software, and design, is owned by AppliFlow
              and protected by copyright, trademark, and other intellectual property laws.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">6.2 User Content License</h3>
            <p>
              By uploading your resume and profile information, you grant AppliFlow a worldwide, non-exclusive, royalty-free
              license to use, reproduce, and distribute your content solely for the purpose of providing the Service.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">6.3 Retention of Rights</h3>
            <p>
              You retain all ownership rights to your content. The license granted to us does not transfer ownership.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">7. Payment and Subscription</h2>
            <h3 className="text-xl font-medium text-foreground mb-2">7.1 Fees</h3>
            <p>
              Certain features of the Service may require payment. All fees are stated in U.S. dollars unless otherwise
              specified.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">7.2 Billing</h3>
            <p>
              If you subscribe to a paid plan, you authorize us to charge your payment method on a recurring basis.
              Fees are non-refundable except as required by law.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">7.3 Cancellation</h3>
            <p>
              You may cancel your subscription at any time through your account settings. Cancellation will take effect
              at the end of your current billing period.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">8. Privacy and Data Protection</h2>
            <p>
              Your use of the Service is also governed by our Privacy Policy, which is incorporated into these Terms by
              reference. Please review our Privacy Policy to understand how we collect, use, and protect your information.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">9. Third-Party Services and Links</h2>
            <p>
              Our Service may integrate with or contain links to third-party services, job boards, and websites. We are
              not responsible for the content, terms, or privacy practices of these third parties. Your interactions with
              third parties are solely between you and them.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">10. Disclaimers and Limitations of Liability</h2>
            <h3 className="text-xl font-medium text-foreground mb-2">10.1 Service "As Is"</h3>
            <p>
              THE SERVICE IS PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR
              IMPLIED, INCLUDING BUT NOT LIMITED TO WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE,
              NON-INFRINGEMENT, OR COURSE OF PERFORMANCE.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">10.2 No Guarantee of Results</h3>
            <p>
              We do not guarantee that the Service will meet your requirements, result in job offers, or be uninterrupted,
              timely, secure, or error-free.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">10.3 Limitation of Liability</h3>
            <p>
              TO THE MAXIMUM EXTENT PERMITTED BY LAW, APPLIFLOW SHALL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL,
              SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES, INCLUDING LOSS OF PROFITS, DATA, OR OPPORTUNITIES, ARISING
              FROM YOUR USE OF THE SERVICE.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">10.4 Maximum Liability</h3>
            <p>
              Our total liability for any claims arising from these Terms or the Service shall not exceed the amount
              you paid to AppliFlow in the 12 months preceding the claim.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">11. Indemnification</h2>
            <p>
              You agree to indemnify, defend, and hold harmless AppliFlow and its officers, directors, employees, and
              agents from any claims, liabilities, damages, losses, costs, or expenses (including reasonable attorneys'
              fees) arising from:
            </p>
            <ul className="list-disc list-inside space-y-2 ml-4">
              <li>Your use of the Service</li>
              <li>Your violation of these Terms</li>
              <li>Your violation of any rights of another party</li>
              <li>Any content you submit through the Service</li>
            </ul>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">12. Termination</h2>
            <h3 className="text-xl font-medium text-foreground mb-2">12.1 Termination by You</h3>
            <p>
              You may terminate your account at any time by contacting us or using the account deletion feature.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">12.2 Termination by Us</h3>
            <p>
              We reserve the right to suspend or terminate your account and access to the Service at any time, with or
              without notice, for any reason, including violation of these Terms.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">12.3 Effect of Termination</h3>
            <p>
              Upon termination, your right to use the Service will immediately cease. Provisions that by their nature
              should survive termination shall survive, including ownership provisions, warranty disclaimers, and
              limitations of liability.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">13. Dispute Resolution and Arbitration</h2>
            <h3 className="text-xl font-medium text-foreground mb-2">13.1 Informal Resolution</h3>
            <p>
              Before filing a claim, you agree to contact us to attempt to resolve the dispute informally.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">13.2 Binding Arbitration</h3>
            <p>
              Any disputes arising from these Terms or the Service shall be resolved through binding arbitration in
              accordance with the rules of the American Arbitration Association, except where prohibited by law.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">13.3 Class Action Waiver</h3>
            <p>
              You agree to resolve disputes on an individual basis and waive the right to participate in class actions
              or class-wide arbitration.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">14. Governing Law</h2>
            <p>
              These Terms shall be governed by and construed in accordance with the laws of the United States, without
              regard to its conflict of law provisions.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">15. Changes to Terms</h2>
            <p>
              We reserve the right to modify these Terms at any time. We will notify you of material changes by posting
              the updated Terms on our platform and updating the effective date. Your continued use of the Service after
              changes constitutes acceptance of the modified Terms.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">16. General Provisions</h2>
            <h3 className="text-xl font-medium text-foreground mb-2">16.1 Entire Agreement</h3>
            <p>
              These Terms, together with our Privacy Policy, constitute the entire agreement between you and AppliFlow
              regarding the Service.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">16.2 Severability</h3>
            <p>
              If any provision of these Terms is found to be invalid or unenforceable, the remaining provisions shall
              remain in full force and effect.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">16.3 Waiver</h3>
            <p>
              Our failure to enforce any provision of these Terms shall not constitute a waiver of that provision or
              our right to enforce it in the future.
            </p>

            <h3 className="text-xl font-medium text-foreground mb-2 mt-4">16.4 Assignment</h3>
            <p>
              You may not assign or transfer these Terms without our prior written consent. We may assign these Terms
              without restriction.
            </p>
          </section>

          <section>
            <h2 className="text-2xl font-semibold text-foreground mb-3">17. Contact Information</h2>
            <p className="mb-2">
              If you have any questions or concerns about these Terms, please contact us at:
            </p>
            <div className="ml-4">
              <p><strong>Email:</strong> legal@appliflow.com</p>
              <p><strong>Address:</strong> AppliFlow HQ</p>
            </div>
          </section>

          <div className="pt-8 border-t border-border mt-8">
            <p className="text-sm">
              These Terms of Service were last updated on {new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
