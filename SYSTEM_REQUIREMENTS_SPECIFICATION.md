# Barangay e-Log System Requirements Specification

**A Course Requirement of Software Engineering 1 (CSC 106)**

**Submitted to:** [Instructor's Name]  
**Submitted by:** [Proponent's Name]  
**Date of Submission:** [Date]

---

## TABLE OF CONTENTS

I. INTRODUCTION
- Overview of the Project
- a. Problem Statement
- b. Objectives of the Study
- c. Scope and Limitations

II. PROJECT BACKGROUND
- a. Description of the Project Domain
- b. Current System or Process
- c. Justification of the Proposed System

III. STAKEHOLDER AND USER ANALYSIS
- A. User Persona
- B. Stakeholder Identification
- C. User Stories and Requirements Analysis
  - 1. User Stories
  - 2. Identification of User Requirements
    - a) Functional Requirements
    - b) Non-Functional Requirements
  - 3. Possible System Requirements
    - a) Hardware Requirements
    - b) Software Requirements
    - c) Database Requirements
    - d) Network and Communication Requirements
    - e) Integration with Existing Systems

IV. PROJECT DEVELOPMENT PLAN
- a. Development Methodology
- b. Timeline and Milestones
- c. Risk Analysis and Mitigation Strategies

V. CONCLUSION
- a. Summary of Key Points
- b. Expected Impact and Benefits of the System

REFERENCES

---

## I. INTRODUCTION

### Overview of the Project

The **Barangay e-Log System** is a web-based application designed to digitize and automate barangay (local government unit) operations in the Philippines. The system addresses the need for efficient citizen service management, appointment scheduling, and official attendance tracking. It provides a centralized platform where citizens can register, book appointments, and view announcements, while barangay officials can manage appointments, track attendance, and generate reports.

The system eliminates paper-based processes, reduces waiting times, improves service delivery, and enhances transparency in barangay operations.

### a. Problem Statement

Traditional barangay operations face several challenges:

1. **Manual Appointment System**: Citizens must physically visit the barangay hall to book appointments, leading to long queues and wasted time.

2. **Paper-Based Records**: Citizen records, appointments, and transactions are maintained manually, making data retrieval difficult and prone to errors.

3. **Inefficient Communication**: Announcements are posted on bulletin boards, limiting reach and accessibility to citizens.

4. **No Attendance Tracking**: Official presence and duty schedules are not systematically tracked, affecting service availability.

5. **Lack of Analytics**: No data-driven insights for decision-making and performance evaluation.

6. **Citizen Verification Issues**: Manual verification processes cause delays in service delivery.

### b. Objectives of the Study

**Primary Objectives:**
1. Develop a web-based system for automated barangay operations
2. Enable online appointment booking for citizens
3. Digitize citizen records and transaction management
4. Implement real-time announcement publishing
5. Automate official attendance tracking
6. Provide analytics and reporting capabilities

**Specific Goals:**
- Reduce appointment booking time by 80%
- Eliminate paper-based record keeping
- Improve citizen service delivery efficiency
- Enable 24/7 access to barangay services
- Provide data-driven insights for decision-making
- Enhance transparency and accountability

### c. Scope and Limitations

**In Scope:**
- Citizen registration and account management
- Online appointment booking and management
- Announcement publishing and viewing
- Official attendance tracking (check-in/check-out)
- Duty roster management
- Citizen verification system
- Transaction logging
- Analytics and reporting
- Audit logging

**Out of Scope:**
- Payment processing integration
- SMS/Email notification system
- Mobile application development
- Integration with external government systems
- Document generation and printing
- Multi-language support
- Real-time chat support

**Limitations:**
- Requires internet connection
- Citizens must have basic computer literacy
- System depends on server availability
- No offline functionality
- Limited to single barangay implementation

---

## II. PROJECT BACKGROUND

### a. Description of the Project Domain

The project operates in the **local government sector**, specifically barangay administration in the Philippines. Barangays are the smallest administrative division, serving as the primary point of contact between citizens and government services. They handle various services including:

- Certificate issuance (Barangay Clearance, Certificate of Residency)
- Document authentication
- Community services
- Public information dissemination
- Citizen record management

The domain requires efficient service delivery, accurate record-keeping, and transparent operations to serve the community effectively.

### b. Current System or Process

**Existing Manual Process:**

1. **Appointment Booking**: Citizens visit barangay hall, fill out forms, and wait for available slots
2. **Record Keeping**: Paper-based files stored in cabinets, difficult to search and maintain
3. **Announcements**: Posted on physical bulletin boards at barangay hall
4. **Attendance**: Manual sign-in sheets, no systematic tracking
5. **Verification**: Physical ID verification during each visit
6. **Reporting**: Manual compilation of statistics and reports

**Limitations:**
- Time-consuming processes
- High risk of data loss
- Limited accessibility
- No centralized data management
- Difficult to generate reports
- No real-time information sharing

### c. Justification of the Proposed System

The proposed system addresses all identified problems:

1. **Efficiency**: Online appointment booking reduces waiting time and eliminates queues
2. **Data Security**: Digital storage with backup prevents data loss
3. **Accessibility**: 24/7 online access from anywhere
4. **Centralization**: All data in one database, easy to manage and retrieve
5. **Analytics**: Automated report generation for data-driven decisions
6. **Transparency**: Real-time updates and public visibility of officials
7. **Scalability**: System can handle growing number of citizens and transactions

---

## III. STAKEHOLDER AND USER ANALYSIS

### A. User Persona

**1. Citizen Persona**
- **Name**: Maria Santos
- **Age**: 35
- **Occupation**: Housewife
- **Tech Literacy**: Basic (uses smartphone, Facebook)
- **Goals**: Book appointments quickly, view announcements, avoid long queues
- **Pain Points**: Long waiting times, need to visit barangay multiple times
- **Needs**: Easy-to-use interface, clear instructions, appointment status updates

**2. Barangay Official Persona**
- **Name**: Juan Dela Cruz
- **Age**: 42
- **Role**: Barangay Secretary
- **Tech Literacy**: Intermediate
- **Goals**: Manage appointments efficiently, track attendance, verify citizens
- **Pain Points**: Manual paperwork, disorganized records, time-consuming processes
- **Needs**: Dashboard overview, quick actions, report generation

**3. Administrator Persona**
- **Name**: Rosa Garcia
- **Age**: 50
- **Role**: Barangay Captain
- **Tech Literacy**: Intermediate
- **Goals**: Monitor system performance, view analytics, manage users
- **Pain Points**: Lack of insights, manual reporting
- **Needs**: Comprehensive analytics, user management, system oversight

### B. Stakeholder Identification

| Stakeholder | Interest | Influence | Priority |
|------------|----------|-----------|----------|
| Citizens | Access to services, convenience | High | High |
| Barangay Officials | Efficient operations, reduced workload | High | High |
| Barangay Captain | System performance, accountability | High | High |
| IT Administrator | System maintenance, security | Medium | Medium |
| Local Government | Compliance, transparency | Medium | Medium |

### C. User Stories and Requirements Analysis

#### 1. User Stories

**Citizen Stories:**
- As a citizen, I want to register online so that I can access barangay services without visiting the hall
- As a citizen, I want to book appointments online so that I can avoid long queues
- As a citizen, I want to view my appointment status so that I know when to visit
- As a citizen, I want to see announcements so that I stay informed about barangay news
- As a citizen, I want to view my appointment history so that I can track past services

**Official Stories:**
- As an official, I want to manage appointments so that I can approve or reschedule requests
- As an official, I want to verify citizens so that only legitimate residents can book
- As an official, I want to check in/out so that my presence is tracked
- As an official, I want to view duty roster so that I know my schedule

**Admin Stories:**
- As an admin, I want to view analytics so that I can make data-driven decisions
- As an admin, I want to manage users so that I can control system access
- As an admin, I want to publish announcements so that citizens are informed
- As an admin, I want to view audit logs so that I can monitor system activity

#### 2. Identification of User Requirements

**a) Functional Requirements**

| ID | Requirement | Priority |
|----|-------------|----------|
| FR1 | System shall allow citizens to register with CIN | High |
| FR2 | System shall require citizen verification before appointment booking | High |
| FR3 | System shall allow citizens to book appointments online | High |
| FR4 | System shall allow officials to approve/decline/reschedule appointments | High |
| FR5 | System shall assign queue numbers to approved appointments | Medium |
| FR6 | System shall allow officials to check-in/check-out | High |
| FR7 | System shall display present officials on public homepage | Medium |
| FR8 | System shall allow admin to publish announcements | High |
| FR9 | System shall allow citizens to view announcements | High |
| FR10 | System shall generate analytics and reports | Medium |
| FR11 | System shall log all user activities | High |
| FR12 | System shall manage user roles and permissions | High |

**b) Non-Functional Requirements**

| ID | Requirement | Priority |
|----|-------------|----------|
| NFR1 | System shall respond within 2 seconds for standard operations | High |
| NFR2 | System shall support 100+ concurrent users | Medium |
| NFR3 | System shall use password hashing for security | High |
| NFR4 | System shall prevent SQL injection attacks | High |
| NFR5 | System shall prevent XSS attacks | High |
| NFR6 | System shall be accessible via web browsers | High |
| NFR7 | System shall be responsive on mobile devices | Medium |
| NFR8 | System shall maintain 99% uptime | Medium |
| NFR9 | System shall backup data daily | High |
| NFR10 | System shall provide audit trail for all actions | High |

#### 3. Possible System Requirements

**a) Hardware Requirements**

**Server:**
- Processor: Intel Core i5 or equivalent
- RAM: 4GB minimum, 8GB recommended
- Storage: 50GB minimum for database and files
- Network: Stable internet connection

**Client:**
- Any device with web browser (PC, laptop, tablet, smartphone)
- Internet connection

**b) Software Requirements**

**Server:**
- Operating System: Windows/Linux
- Web Server: Apache 2.4+ or Nginx
- PHP: Version 7.4 or higher
- Database: MySQL 5.7+ or MariaDB 10.3+
- PHP Extensions: mysqli, session, mbstring

**Client:**
- Web Browser: Chrome, Firefox, Edge, Safari (latest versions)
- JavaScript enabled

**Development:**
- Code Editor: VS Code, PHPStorm, or similar
- Version Control: Git
- Local Server: XAMPP/WAMP/LAMP

**c) Database Requirements**

- Database Name: `elog_barangay`
- Character Set: UTF-8 (utf8mb4)
- Storage Engine: InnoDB
- Required Tables: 9 core tables (users, citizens, appointments, announcements, duty_roster, presence, transactions, documents, audit_logs)
- Backup: Daily automated backups recommended

**d) Network and Communication Requirements**

- Internet connection for web access
- HTTPS recommended for production
- Firewall configuration for security
- Port 80 (HTTP) or 443 (HTTPS) open

**e) Integration with Existing Systems**

Currently, the system operates as a standalone application. Future integration possibilities:
- Government database systems (for citizen verification)
- Payment gateways (for online payments)
- SMS/Email services (for notifications)

---

## IV. PROJECT DEVELOPMENT PLAN

### a. Development Methodology

**Agile Methodology** with iterative development cycles:

- **Sprint Duration**: 2 weeks
- **Daily Standups**: Progress updates
- **Sprint Reviews**: Feature demonstrations
- **Retrospectives**: Process improvements

**Phases:**
1. Planning and Requirements Gathering
2. Design and Prototyping
3. Development (Backend, Frontend, Database)
4. Testing (Unit, Integration, User Acceptance)
5. Deployment and Training
6. Maintenance and Support

### b. Timeline and Milestones

```
┌─────────────────────────────────────────────────────────────┐
│                    PROJECT TIMELINE                         │
└─────────────────────────────────────────────────────────────┘

Month 1: Planning & Design
├─ Week 1-2: Requirements Analysis
├─ Week 3: Database Design
└─ Week 4: UI/UX Design

Month 2: Development Phase 1
├─ Week 1-2: Authentication & User Management
├─ Week 3: Citizen Portal (Registration, Dashboard)
└─ Week 4: Appointment Booking System

Month 3: Development Phase 2
├─ Week 1-2: Admin Portal (Dashboard, Appointments)
├─ Week 3: Announcement System
└─ Week 4: Attendance Tracking

Month 4: Development Phase 3
├─ Week 1-2: Analytics & Reporting
├─ Week 3: Testing & Bug Fixes
└─ Week 4: Deployment & Training

Key Milestones:
✓ Requirements Finalized
✓ Database Schema Completed
✓ Core Features Implemented
✓ Testing Completed
✓ System Deployed
```

### c. Risk Analysis and Mitigation Strategies

| Risk | Impact | Probability | Mitigation Strategy |
|------|--------|-------------|-------------------|
| Data Loss | High | Low | Daily automated backups, redundant storage |
| Security Breach | High | Medium | Regular security audits, password policies, SQL injection prevention |
| System Downtime | Medium | Medium | Server monitoring, backup server, maintenance windows |
| User Adoption | Medium | Medium | Training sessions, user-friendly interface, support |
| Technical Issues | Medium | High | Comprehensive testing, code reviews, documentation |
| Internet Connectivity | Low | Medium | Offline capability (future), local server option |

---

## V. CONCLUSION

### a. Summary of Key Points

The Barangay e-Log System addresses critical inefficiencies in traditional barangay operations by:

1. **Digitizing Operations**: Moving from paper-based to digital processes
2. **Improving Accessibility**: Enabling 24/7 online access to services
3. **Enhancing Efficiency**: Reducing waiting times and streamlining workflows
4. **Ensuring Security**: Implementing robust security measures and audit trails
5. **Providing Insights**: Generating analytics for data-driven decision-making

The system successfully automates appointment booking, citizen management, announcement publishing, and attendance tracking, significantly improving service delivery and operational efficiency.

### b. Expected Impact and Benefits of the System

**For Citizens:**
- 80% reduction in appointment booking time
- 24/7 access to barangay services
- Real-time appointment status updates
- Easy access to announcements
- Reduced need for multiple visits

**For Barangay Officials:**
- 60% reduction in paperwork
- Efficient appointment management
- Automated attendance tracking
- Quick access to citizen records
- Data-driven insights for planning

**For Barangay Administration:**
- Improved service delivery metrics
- Enhanced transparency and accountability
- Better resource allocation
- Comprehensive audit trail
- Professional image and modernization

**Overall Impact:**
- Modernized barangay operations
- Improved citizen satisfaction
- Increased operational efficiency
- Better data management
- Foundation for future digital initiatives

---

## REFERENCES

1. PHP Documentation. (2024). PHP Manual. https://www.php.net/docs.php
2. MySQL Documentation. (2024). MySQL Reference Manual. https://dev.mysql.com/doc/
3. Bootstrap Documentation. (2024). Bootstrap 5. https://getbootstrap.com/docs/5.0/
4. Chart.js Documentation. (2024). Chart.js. https://www.chartjs.org/docs/
5. Software Engineering Institute. (2024). Requirements Engineering. Carnegie Mellon University.
6. Local Government Code of the Philippines. (1991). Republic Act No. 7160.

---

## APPENDICES

### Appendix A: Entity Relationship Diagram (ERD)
*See SYSTEM_OVERVIEW.md Section "Entity Relationship Diagram (ERD)"*

### Appendix B: Use Case Diagram
*See SYSTEM_OVERVIEW.md Section "Use Case Diagram"*

### Appendix C: Data Flow Diagram (DFD)
*See SYSTEM_OVERVIEW.md Section "Data Flow Diagram (DFD)"*

### Appendix D: Database Schema
*See schema.sql file*

---

**Document Version:** 1.0  
**Last Updated:** [Date]  
**Status:** Complete

