# SkillTrust Student Jobs System - IMPLEMENTATION TODO

## Status: 🚀 In Progress (0/7 complete)

### 1. [✅] Create student/jobs.php
   - List active jobs with eligibility (AVG(score) >= min_average_score)
   - Badges: Eligible/Not Eligible
   - Apply button (AJAX if eligible)
   - Tabs: All/Eligible, search/pagination

### 2. [✅] Create student/apply_job.php (AJAX)
   - Secure POST, INSERT application (prevent dup)
   - Snapshot AVG score
   - JSON response w/ toast
   - Secure POST, INSERT application (prevent dup)
   - Snapshot AVG score
   - JSON response w/ toast

### 3. [ ] Create student/applied_jobs.php
   - List user's applications by status
   - Badges, recruiter info, dates

### 4. [ ] Create assets/css/student/jobs.css
   - Tailwind matching recruiter (glassmorphism, responsive)

### 5. [ ] Create assets/js/student/jobs.js
   - Dark/light toggle (localStorage)
   - AJAX apply, toasts, form validation, badges

### 6. [ ] Add navbar links
   - student/dashboard.php: Link to jobs.php/applied_jobs.php

### 7. [ ] Test & Verify
   - Student login → jobs.php → apply → recruiter applicants.php sees it
   - Edge: No tests=ineligible, expired jobs hidden, dup apply prevented

## Next Step: student/jobs.php

**Production-ready, secure, SaaS UI matching recruiter panel.**

