![Banner](./assets/src/images/banner.svg)

# OneAccess - Enterprise way to manage users across multiple sites

**Contributors:** [rtCamp](https://profiles.wordpress.org/rtcamp), [up1512001](https://github.com/up1512001), [parthnvaswani](https://github.com/parthnvaswani), [danish17](https://github.com/danish17), [vishalkakadiya](https://github.com/vishalkakadiya), [aviral-mittal](https://github.com/aviral-mittal), [rishavjeet](https://github.com/rishavjeet), [vishal4669](https://github.com/vishal4669), [SushantKakade](https://github.com/SushantKakade)

**Tags:** OneAccess, OnePress, WordPress, User Manager, Multi-site, User Administration, Enterprise User Management

This plugin is licensed under the GPL v2 or later.

## Overview

OneAccess is a centralized user management solution designed for enterprises managing multiple WordPress sites. It streamlines user creation, role assignment, and profile management across multiple WordPress installations by providing a single governing interface to manage users on all connected brand sites.

## Description

**OneAccess** is a centralized user manager for WordPress. From a single governing site, you can:

* **Create and manage users** across multiple WordPress sites simultaneously
* **Assign and modify user roles** on selected sites with granular control
* **Handle profile update requests** with approval workflows
* **Bulk operations** for user management across multiple sites
* **Secure API communication** between governing and brand sites

This makes it easier to maintain consistent user access, roles, and profiles across all your WordPress environments while ensuring proper approval workflows for sensitive changes.

## Why OneAccess?

Managing users across multiple WordPress sites in enterprise environments is a complex and time-consuming process. Manual user creation, role assignment, and profile updates across different sites can lead to:

- Inconsistent user access across sites
- Administrative overhead and human errors
- Lack of centralized control over user permissions
- No approval workflow for profile changes

OneAccess solves this by:

- **Centralizing User Management:** Single governing site to manage users across all brand sites
- **Automated User Provisioning:** Create users and assign roles to multiple sites simultaneously
- **Approval Workflows:** Controlled profile update process with admin approval
- **Role-Based Access Control:** Custom roles for governing and brand site administration
- **Secure Communication:** All operations use nonce and secure API keys via WordPress REST API

### Key Benefits

- **Reduced Administrative Overhead:** Manage all users from a single dashboard
- **Consistent User Experience:** Ensure uniform access and roles across all sites
- **Enhanced Security:** Approval workflows prevent unauthorized profile changes
- **Scalable Architecture:** Handle users across unlimited number of sites

## Features

### Core Functionality

- **Centralized User Dashboard:** Comprehensive interface for user management operations
- **Multi-Site User Creation:** Create users and assign to multiple sites in one action
- **Role Management:** Assign and modify user roles on selective sites
- **Profile Request System:** Approval workflow for user profile updates
- **Site-Selective Actions:** Target specific sites for user operations

### User Management Actions

- **User Creation:** Create new users with customizable roles across selected sites
- **Role Assignment:** Modify user roles on existing sites or assign users to new sites
- **User Removal:** Remove users from selective sites while maintaining access on others
- **Profile Approvals:** Review and approve/reject profile update requests
- **Bulk Operations:** Manage multiple users across multiple sites simultaneously

### Security Features

- **Secure API Communication:** All operations use WordPress REST API with nonce validation
- **API Key Authentication:** Unique API keys for secure site-to-site communication
- **Role-Based Permissions:** Custom user roles with specific capabilities
- **Approval Workflows:** Controlled process for sensitive profile changes

## System Requirements

| Requirement | Version |
| :---- | :---- |
| WordPress | >= 6.9 |
| PHP | >= 8.2 |

## Installation & Setup

For detailed installation instructions, system requirements, and step-by-step configuration guides, please see our comprehensive [**Installation and Setup Guide**](./docs/INSTALLATION.md).

## Usage Guide

### Accessing the User Management Dashboard

Navigate to **OneAccess → User Manager** in your WordPress admin (governing site only) to access the centralized management interface.

### Dashboard Tabs

#### 1. Users Tab

View and manage all users created through OneAccess:

- **View All Users:** Complete list of users managed by OneAccess
- **Site Assignment:** Add users to new sites where they're not currently present
- **Role Management:** Change user roles on sites where they're already assigned
- **User Removal:** Remove users from selective sites
- **Site-Selective Actions:** Target specific sites for all operations

#### 2. Create User Tab

Create new users and assign them to multiple sites:

**User Creation Form:**

- **Username:** Unique identifier for the user
- **Email Address:** User's email address
- **Full Name:** User's display name
- **User Role:** Select appropriate role for the user
- **Password:**
  - Manual entry or generate strong password
  - Password strength validation (weak/very-weak passwords rejected)
  - Show/hide password option

**Site Assignment:**

- **Assign to Sites** button (activated after all required fields are completed)
- **Select Target Sites:** Choose specific sites or select all sites
- **Bulk Assignment:** Add user to multiple sites simultaneously

#### 3. Profile Requests Tab

Manage user profile update requests with approval workflow:

**Request Types:**

- **User-Generated Requests:** Users requesting changes to their own profiles
- **Brand Admin Requests:** Brand admins requesting changes for any user

**Approval Process:**

- **Pending Requests:** Review profile change requests
- **Approve/Reject:** Network admin can approve or reject requests
- **Rejection Comments:** Add comments when rejecting requests
- **User Notifications:** Users see rejection reasons and can resubmit

**Profile Lock Mechanism:**

- **Pending Request Lock:** Users cannot edit profiles while requests are pending
- **Notification Display:** "Your profile update is pending approval" message
- **Edit Restriction:** Profile editing disabled until approval/rejection

## Security & Communication

### API Security

- **WordPress REST API:** All communication uses standard WordPress REST API
- **Nonce Validation:** Every request includes nonce for CSRF protection
- **API Key Authentication:** Unique keys for each brand site connection
- **Secure Transmission:** All data transmitted securely between sites

### User Permissions

- **Network Admin (Governing Site):**

  - Full user management capabilities
  - Profile request approvals
  - Site configuration management


- **Brand Admin (Brand Sites):**

  - Profile change requests for users
  - Local user administration
  - Limited management capabilities

### Approval Workflows

- **Profile Change Requests:** All profile updates require Network Admin approval
- **Comment System:** Rejection reasons visible to requesting users
- **Resubmission Process:** Users can modify and resubmit rejected requests

## Development & Contributing

OneAccess is actively developed and maintained by [rtCamp](https://rtcamp.com/).

- **Repository:** [github.com/rtCamp/OneAccess](https://github.com/rtCamp/OneAccess)
- **Contributing Guide:** [docs/CONTRIBUTING.md](./docs/CONTRIBUTING.md)
- **Development Guide:** [docs/DEVELOPMENT.md](./docs/DEVELOPMENT.md)

We welcome contributions! Please read our contributing guidelines before submitting pull requests.

### Workflow Overview

1. **Installation Phase:** Install OneAccess on governing site and all brand sites
2. **Configuration Phase:** Set site types and configure brand sites to connect with governing site
3. **User Management:** Network Admin manages users through governing site dashboard
4. **Profile Requests:** Users and Brand Admins submit profile change requests
5. **Approval Process:** Network Admin reviews and approves/rejects profile changes
6. **Synchronization:** All changes are synchronized across connected sites via secure API

## Frequently Asked Questions

### How are users synchronized between sites?

Users are synchronized using WordPress REST API with secure authentication. All operations are performed in real-time with immediate updates across connected sites.

### Can brand site admins create users directly?

No, all user creation must be done through the governing site to maintain consistency and central control.

### What happens if a profile request is rejected?

When a profile request is rejected, the Network Admin can add comments explaining the rejection. These comments are visible to the user, who can then make necessary changes and resubmit the request.

### Can users edit their profiles while a request is pending?

No, when a profile update request is pending, all profile editing is disabled until the request is approved or rejected by the Network Admin.

### How are passwords handled for new users?

Passwords can be manually entered or auto-generated using the strong password generator. The system enforces password strength requirements and provides options to show/hide passwords during creation.

### Can I remove users from specific sites only?

Yes, you can remove users from selective sites while maintaining their access on other sites. This provides granular control over user access across your site network.

### What happens to my existing users?

Pre-existing users will continue to exist, they will now only be manageable via the Governing site. When a new brand site is connected, existing users are automatically synced to the governing site for centralized management.

## Troubleshooting

### Users Not Appearing on Brand Sites

- Verify API key configuration on brand sites
- Check network connectivity between governing and brand sites
- Confirm REST API endpoints are accessible
- Ensure proper user roles (Network Admin/Brand Admin)

### Profile Requests Not Working

- Verify nonce validation is working correctly
- Check API key permissions and configuration
- Confirm user roles and capabilities
- Review WordPress REST API functionality

### Connection Issues Between Sites

- **API Key Errors:** Regenerate and reconfigure API keys
- **Network Connectivity:** Test direct connection between sites
- **WordPress REST API:** Ensure REST API is enabled and accessible
- **SSL/HTTPS Issues:** Verify secure connections if using HTTPS

### Common Issues

- **Role Assignment Failures:** Check user capabilities and site permissions
- **Bulk Operation Timeouts:** Consider processing smaller batches for large user sets
- **Profile Lock Issues:** Clear pending requests or approve/reject to unlock profiles
- **Site Connection Failures:** Verify governing site URL and API configuration

## Support & Community

- **Issues & Bug Reports:** [GitHub Issues](https://github.com/rtCamp/OneAccess/issues)
- **Feature Requests:** [GitHub Discussions](https://github.com/rtCamp/OneAccess/discussions)
- **Documentation:** [Project Wiki](https://github.com/rtCamp/OneAccess/wiki)

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](./LICENSE) file for details.

---

**Made with ❤️ by [rtCamp](https://rtcamp.com/)**
