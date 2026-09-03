=== OneAccess ===
Contributors: Utsav Patel, rtCamp
Donate link: https://rtcamp.com/
Tags: OneAccess, OnePress, User Manager, Multi-site, Enterprise User Management
Requires at least: 6.8
Tested up to: 6.9
<!-- x-release-please-start-version -->
Stable tag: 2.0.0
<!-- x-release-please-end -->
Requires PHP: 8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Centralized user management: create, manage, and control user access across multiple WordPress sites from one governing site.

== Description ==

OneAccess is a powerful user management solution designed for enterprises managing multiple WordPress sites. It provides centralized user creation, role assignment, and profile management across multiple WordPress installations through a single governing interface with approval workflows.

**Why OneAccess?**

Managing users across multiple WordPress sites in enterprise environments is complex and time-consuming. Manual user creation, role assignment, and profile updates across different sites can lead to inconsistent user access, administrative overhead, and lack of centralized control.

OneAccess solves this by providing a single governing site to manage users across all connected brand sites, with secure API communication and approval workflows for sensitive changes.

**Key Benefits:**

* **Reduced Administrative Overhead:** Manage all users from a single dashboard
* **Consistent User Experience:** Ensure uniform access and roles across all sites
* **Enhanced Security:** Approval workflows prevent unauthorized profile changes
* **Scalable Architecture:** Handle users across unlimited number of sites

**Core Features:**

* **Centralized User Dashboard:** Comprehensive interface for user management operations
* **Multi-Site User Creation:** Create users and assign to multiple sites in one action
* **Role Management:** Assign and modify user roles on selective sites
* **Profile Request System:** Approval workflow for user profile updates
* **Site-Selective Actions:** Target specific sites for user operations

**User Management Actions:**

* Create new users with customizable roles across selected sites
* Modify user roles on existing sites or assign users to new sites
* Remove users from selective sites while maintaining access on others
* Review and approve/reject profile update requests
* Perform bulk operations across multiple sites
* Manage user access with granular site-specific control

**Security Features:**

* **Secure API Communication:** All operations use WordPress REST API with nonce validation
* **API Key Authentication:** Unique API keys for secure site-to-site communication
* **Role-Based Permissions:** Custom user roles (Network Admin, Brand Admin) with specific capabilities
* **Approval Workflows:** Controlled process for sensitive profile changes

**Perfect for:**

* Enterprise WordPress deployments with multiple sites
* Organizations requiring centralized user management
* Companies with strict user approval processes
* Agencies managing multiple client sites
* Educational institutions with multiple campus sites

== Installation ==

1. Upload the OneAccess plugin files to the `/wp-content/plugins/oneaccess` directory, or install the plugin through the WordPress plugins screen directly
2. Activate the plugin through the 'Plugins' screen in WordPress
3. **Choose Site Type (One-time selection):**
   * **Governing Site:** Central management dashboard
   * **Brand Site:** Managed remote site
4. **For Governing Site:** Access OneAccess → User Manager in WordPress admin
5. **For Brand Sites:** Configure connection to governing site:
   * Enter Site Name, Site URL, and API Key in OneAccess Settings
6. **User Role Requirements:**
   * Governing Site: Network Admin role (automatically created)
   * Brand Sites: Brand Admin role (automatically created)

**Important:** Site type selection is permanent and can only be changed by completely removing the plugin.

== Frequently Asked Questions ==

= How are users synchronized between sites? =

Users are synchronized using WordPress REST API with secure authentication. All operations are performed in real-time with immediate updates across connected sites.

= Can brand site admins create users directly? =

No, all user creation must be done through the governing site to maintain consistency and central control.

= What happens if a profile request is rejected? =

When a profile request is rejected, the Network Admin can add comments explaining the rejection. These comments are visible to the user, who can then make necessary changes and resubmit the request.

= Can users edit their profiles while a request is pending? =

No, when a profile update request is pending, all profile editing is disabled until the request is approved or rejected by the Network Admin.

= How are passwords handled for new users? =

Passwords can be manually entered or auto-generated using the strong password generator. The system enforces password strength requirements and provides options to show/hide passwords during creation.

= Can I remove users from specific sites only? =

Yes, you can remove users from selective sites while maintaining their access on other sites. This provides granular control over user access across your site network.

= Does this work with WordPress multisite? =

OneAccess is designed to work across multiple separate WordPress installations. It's different from WordPress multisite - it connects independent WordPress sites through secure API communication.

= Is there a limit to how many sites I can manage? =

There are no hard limits on the number of brand sites you can connect to your governing site.

= What user roles are created by OneAccess? =

OneAccess creates two custom roles: Network Admin (for governing site administration) and Brand Admin (for brand site administration).

== Screenshots ==

1. OneAccess Dashboard - Centralized user management interface showing Users, Create User, and Profile Requests tabs
2. Users Tab - View and manage all users with site assignment and role management options
3. Create User Tab - User creation form with password generation and site assignment
4. Profile Requests Tab - Approval workflow for profile update requests
5. Site Configuration - Brand site connection setup with API key configuration

== Changelog ==

See <a href="https://github.com/rtCamp/OneAccess/blob/main/CHANGELOG.md" target="_blank">CHANGELOG.md</a> for detailed changelog.

== Upgrade Notice ==

= 1.0.0-beta =
Initial release of OneAccess. Perfect for enterprises managing users across multiple WordPress sites with centralized control and approval workflows.

== Requirements ==

* Multiple WordPress installations for full functionality
* Network connectivity between governing and brand sites
* WordPress REST API enabled on all sites

== Support ==

For support, feature requests, and bug reports, please visit our [GitHub repository](https://github.com/rtCamp/OneAccess).

== Contributing ==

OneAccess is open source and welcomes contributions. Visit our [GitHub repository](https://github.com/rtCamp/OneAccess) to contribute code, report issues, or suggest features.

Development guidelines and contributing information can be found in our repository documentation.
