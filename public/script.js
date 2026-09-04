let state = {
    user: null,
    currentTopicIndex: null,
    currentLessonIndex: 0,
    completedTopics: [],
    topicProgressMap: {},
    hasPassedMidterm: false,
    examType: 'quiz',
    voucherCode: localStorage.getItem('cssm_voucher') || null,
    courseUnlocked: false,
    hasBoughtVoucher: false,
    certificates: [],
    showEnrolledOnly: false,
    courseLayout: 'list'
};

let courses = [];
let topics = [];
let subjects = [];
let courseMockExamQuestionCount = 0;
let courseMockExamLatestResult = null;
let courseMockExamAttemptsUsed = 0;
let courseMockExamMaximumAttempts = null;
let courseMockExamTimeLimitMinutes = null;
let courseMockExamPassed = false;
let courseMockExamCertificateAvailable = false;
let currentSubjectId = null;
let finalExam = [];
let currentCourseId = null;
let currentBatchId = null;
let selectedPurchaseCourseId = null;
let selectedPurchaseBatchId = null;

// ─── CSRF & API Helpers ───────────────────────────────────
function getCsrfToken() {
    const name = 'XSRF-TOKEN=';
    const decodedCookie = decodeURIComponent(document.cookie);
    const ca = decodedCookie.split(';');
    for(let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == ' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length, c.length);
        }
    }
    return null;
}

async function apiRequest(url, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken()
        }
    };

    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(url, options);
        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || 'Something went wrong with the request.');
        }

        return result;
    } catch (error) {
        showToast(error.message);
        throw error;
    }
}

// ─── Toast System ────────────────────────────────────────
function showToast(msg, type = 'error') {
    const container = $('toast-container');
    if (!container) return console.error("Toast container not found");
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<span>${msg}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// ─── Utils ───────────────────────────────────────────────
const $ = id => document.getElementById(id);

function showScreen(id) {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    const el = $(id);
    if (el) el.classList.add('active');
    window.scrollTo(0, 0);
}

// ─── Modal System ────────────────────────────────────────
const overlay = $('modal-overlay');
const MODALS = ['modal-signup','modal-registration-code','modal-login','modal-forgot','modal-buy-voucher','modal-enter-voucher','modal-mock-exam-instructions','modal-learner-settings','modal-email-change-code','modal-system-alert','modal-assessment-rationale','modal-course-profile'];

function openModal(id) {
    if (!overlay) return;
    overlay.classList.remove('hidden');
    MODALS.forEach(m => {
        const el = $(m);
        if (el) el.classList.add('hidden');
    });
    const target = $(id);
    if (target) target.classList.remove('hidden');
}

function closeModal() {
    if (!overlay) return;
    overlay.classList.add('hidden');
    MODALS.forEach(m => {
        const el = $(m);
        if (el) el.classList.add('hidden');
    });
}

if (overlay) {
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
}

document.querySelectorAll('[data-close]').forEach(btn => {
    btn.addEventListener('click', closeModal);
});

function showSystemAlert(message, title = 'Notice') {
    $('system-alert-title').textContent = title;
    $('system-alert-message').textContent = String(message ?? '');
    openModal('modal-system-alert');
}
const systemAlertOk = $('system-alert-ok');
if (systemAlertOk) systemAlertOk.addEventListener('click', closeModal);

function showCourseProfile() {
    const profile = courses.find(course => Number(course.batch_id) === Number(currentBatchId))
        || courses.find(course => Number(course.id) === Number(currentCourseId) && course.is_enrolled);
    if (!profile) return showSystemAlert('The selected batch profile could not be loaded.');
    const dateLabel = value => value ? new Date(value).toLocaleDateString(undefined,{year:'numeric',month:'long',day:'numeric'}) : 'Not specified';
    const dateTimeLabel = value => value ? new Date(value).toLocaleString(undefined,{year:'numeric',month:'long',day:'numeric',hour:'numeric',minute:'2-digit'}) : 'Not specified';
    const timeLabel = value => {
        if (!value) return 'Not specified';
        const parts = String(value).split(':');
        const date = new Date(2000,0,1,Number(parts[0] || 0),Number(parts[1] || 0));
        return date.toLocaleTimeString(undefined,{hour:'numeric',minute:'2-digit'});
    };
    $('course-profile-title').textContent = profile.batch_name || 'Course Profile';
    $('course-profile-subtitle').textContent = `${profile.batch_code || 'Batch'} · ${profile.title || 'Assigned course'}`;
    $('course-profile-content').innerHTML = `
        <section class="course-profile-summary"><div><span>Assigned Master Course</span><strong>${escapeHtml(profile.title || '--')}</strong></div><div><span>Batch Status</span><strong>${escapeHtml(String(profile.batch_status || 'open').replace(/_/g,' ').replace(/\b\w/g, letter => letter.toUpperCase()))}</strong></div></section>
        <section class="course-profile-description"><span>Description</span><p>${escapeHtml(profile.batch_description || profile.description || 'No description provided.')}</p></section>
        <section class="course-profile-grid">
            <div><span>Batch Code</span><strong>${escapeHtml(profile.batch_code || '--')}</strong></div>
            <div><span>Modality</span><strong>${escapeHtml(profile.batch_modality || 'Not specified')}</strong></div>
            <div><span>Start Date</span><strong>${dateLabel(profile.batch_starts_at)}</strong></div>
            <div><span>End Date</span><strong>${dateLabel(profile.batch_ends_at)}</strong></div>
            <div><span>Schedule Day</span><strong>${escapeHtml(profile.batch_schedule_day || 'Not specified')}</strong></div>
            <div><span>Class Time</span><strong>${timeLabel(profile.batch_start_time)} – ${timeLabel(profile.batch_end_time)}</strong></div>
            <div><span>Price (PHP)</span><strong>₱${Number(profile.batch_price || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</strong></div>
            <div><span>Price (USD)</span><strong>${profile.batch_usd_price !== null && profile.batch_usd_price !== undefined ? '$'+Number(profile.batch_usd_price).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) : 'Not specified'}</strong></div>
            <div><span>Capacity</span><strong>${profile.batch_capacity ? Number(profile.batch_capacity).toLocaleString()+' learners' : 'Unlimited'}</strong></div>
            <div><span>Enrollment Date</span><strong>${dateTimeLabel(profile.enrolled_at)}</strong></div>
            <div><span>Access Expires</span><strong>${profile.enrollment_expires_at ? dateTimeLabel(profile.enrollment_expires_at) : 'No expiration set'}</strong></div>
            <div><span>Enrollment Status</span><strong>${profile.is_enrolled ? 'Active' : 'Not enrolled'}</strong></div>
        </section>`;
    openModal('modal-course-profile');
}

const courseProfileButton = $('course-profile-btn');
if (courseProfileButton) courseProfileButton.addEventListener('click', showCourseProfile);

async function showLearnerProgressReport() {
    if (!currentCourseId) return;
    setCourseDetailsTab('progress');
    const content = $('learner-progress-report-content');
    if (content) content.innerHTML = '<p class="progress-report-empty">Loading assessment history...</p>';
    const result = await apiRequest(`/api/courses/${currentCourseId}/progress-report`);
    if (!result?.success || !content) return;
    const context = $('learner-progress-report-context');
    if (context) context.textContent = `${result.course?.title || 'Course'} · ${result.batch?.name || 'Active batch'}`;
    const attempts = Array.isArray(result.attempts) ? result.attempts : [];
    if (!attempts.length) {
        content.innerHTML = '<p class="progress-report-empty">No assessment attempts have been recorded for this course yet.</p>';
        return;
    }
    const subjectGroups = new Map();
    attempts.forEach(attempt => {
        const subjectKey = attempt.subjectId ? `subject-${attempt.subjectId}` : 'course-mock-exam';
        if (!subjectGroups.has(subjectKey)) subjectGroups.set(subjectKey, {
            title: attempt.subjectId ? `${attempt.subjectCode || ''} ${attempt.subjectTitle || 'Subject'}`.trim() : 'Course Mock Exam',
            assessments: new Map(),
        });
        const group = subjectGroups.get(subjectKey);
        if (!group.assessments.has(attempt.assessmentKey)) group.assessments.set(attempt.assessmentKey, {
            title: attempt.assessmentTitle || attempt.assessmentLabel,
            label: attempt.assessmentLabel,
            topic: attempt.topicTitle,
            attempts: [],
        });
        group.assessments.get(attempt.assessmentKey).attempts.push(attempt);
    });
    content.innerHTML = [...subjectGroups.values()].map(subject => `
        <section class="progress-report-subject">
            <h3>${escapeHtml(subject.title)}</h3>
            ${[...subject.assessments.values()].map(assessment => `
                <details class="progress-report-assessment" open>
                    <summary><strong>${escapeHtml(assessment.label)} · ${escapeHtml(assessment.title)}</strong><span>${assessment.attempts.length} ${assessment.attempts.length === 1 ? 'Attempt' : 'Attempts'}</span></summary>
                    ${assessment.topic ? `<p class="progress-report-topic">Topic: ${escapeHtml(assessment.topic)}</p>` : ''}
                    <div class="progress-report-attempts">${assessment.attempts.map(attempt => {
                        const taken = attempt.takenAt ? new Date(attempt.takenAt).toLocaleString(undefined,{year:'numeric',month:'long',day:'numeric',hour:'numeric',minute:'2-digit'}) : '--';
                        return `<div class="progress-report-attempt"><strong>Attempt ${attempt.attemptNumber}</strong><span>${Number(attempt.score).toLocaleString(undefined,{maximumFractionDigits:2})} out of ${Number(attempt.total).toLocaleString(undefined,{maximumFractionDigits:2})} · ${attempt.percentage}%</span><strong class="${attempt.passed ? 'passed' : 'failed'}">${attempt.passed ? 'Passed' : 'Not Passed'}</strong><time>${escapeHtml(taken)}</time><span class="progress-rank"><small>Batch Rank</small><strong>${attempt.batchRank?.rank || '--'} out of ${attempt.batchRank?.total || '--'}</strong></span><span class="progress-rank"><small>Course-wide Rank</small><strong>${attempt.courseRank?.rank || '--'} out of ${attempt.courseRank?.total || '--'}</strong></span></div>`;
                    }).join('')}</div>
                </details>`).join('')}
        </section>`).join('');
}

function setCourseDetailsTab(tab) {
    const isProgress = tab === 'progress';
    const subjectsArea = $('subjects-list-area');
    const topicsArea = $('subject-topics-area');
    const reportArea = $('course-progress-report-area');
    if (subjectsArea) subjectsArea.style.display = isProgress ? 'none' : '';
    if (topicsArea) topicsArea.style.display = 'none';
    if (reportArea) reportArea.style.display = isProgress ? '' : 'none';
    $('course-subjects-tab')?.classList.toggle('active', !isProgress);
    $('course-progress-tab')?.classList.toggle('active', isProgress);
    if (!isProgress) currentSubjectId = null;
    const backToSubjects = $('back-to-subjects-btn');
    if (backToSubjects) backToSubjects.classList.add('hidden');
}

const courseSubjectsTab = $('course-subjects-tab');
if (courseSubjectsTab) courseSubjectsTab.addEventListener('click', () => { setCourseDetailsTab('subjects'); renderSubjects(); });
const courseProgressTab = $('course-progress-tab');
if (courseProgressTab) courseProgressTab.addEventListener('click', showLearnerProgressReport);
window.alert = message => showSystemAlert(message);

const learnerSettingsBtn = $('learner-settings-btn');
if (learnerSettingsBtn) learnerSettingsBtn.addEventListener('click', () => {
    $('settings-email').value = state.user?.email || '';
    $('settings-phone').value = state.user?.phone || '';
    $('settings-current-password').value = '';
    $('settings-new-password').value = '';
    $('settings-password-confirmation').value = '';
    openModal('modal-learner-settings');
});

const saveLearnerSettings = $('save-learner-settings');
if (saveLearnerSettings) saveLearnerSettings.addEventListener('click', async () => {
    const password = $('settings-new-password').value;
    const confirmation = $('settings-password-confirmation').value;
    if (password !== confirmation) return showToast('The new password confirmation does not match.');
    saveLearnerSettings.disabled = true;
    saveLearnerSettings.textContent = 'Saving…';
    try {
        const result = await apiRequest('/api/profile/settings', 'POST', {
            email: $('settings-email').value.trim(), phone: $('settings-phone').value.trim(),
            current_password: $('settings-current-password').value, password,
            password_confirmation: confirmation
        });
        if (result?.success) {
            state.user.email = result.user.email;
            state.user.phone = result.user.phone;
            const panelEmail = $('panel-email');
            if (panelEmail) panelEmail.textContent = result.user.email;
            if (result.verification_required) {
                $('email-change-pending-address').textContent = result.pending_email;
                $('email-change-code').value = '';
                openModal('modal-email-change-code');
                setTimeout(() => $('email-change-code')?.focus(), 50);
            } else {
                closeModal();
                showToast(result.message, 'success');
            }
        }
    } finally {
        saveLearnerSettings.disabled = false;
        saveLearnerSettings.textContent = 'Save Settings';
    }
});

const verifyEmailChange = $('verify-email-change');
if (verifyEmailChange) verifyEmailChange.addEventListener('click', async () => {
    const code = $('email-change-code').value.replace(/\D/g, '');
    if (code.length !== 6) return showToast('Enter the complete six-digit verification code.');
    verifyEmailChange.disabled = true;
    verifyEmailChange.textContent = 'Verifying…';
    try {
        const result = await apiRequest('/api/profile/email/verify', 'POST', {code});
        if (result?.success) {
            state.user.email = result.user.email;
            const panelEmail = $('panel-email');
            if (panelEmail) panelEmail.textContent = result.user.email;
            closeModal();
            showToast(result.message, 'success');
        }
    } finally {
        verifyEmailChange.disabled = false;
        verifyEmailChange.textContent = 'Verify Email';
    }
});

const resendEmailChange = $('resend-email-change');
if (resendEmailChange) resendEmailChange.addEventListener('click', async () => {
    resendEmailChange.disabled = true;
    try {
        const result = await apiRequest('/api/profile/email/resend', 'POST', {});
        if (result?.success) showToast(result.message, 'success');
    } finally {
        setTimeout(() => { resendEmailChange.disabled = false; }, 60000);
    }
});

// ─── Password Toggles ────────────────────────────────────
document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = $(btn.dataset.target);
        if (!input) return;
        const hide = input.type === 'password';
        input.type = hide ? 'text' : 'password';
        btn.textContent = hide ? 'Hide' : 'Show';
    });
});

// ─── Birthdate MM/DD/YYYY auto-format ────────────────────
const bdateInput = $('su-bdate');
if (bdateInput) {
    bdateInput.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 8);
        if (v.length >= 5)      v = v.slice(0,2) + '/' + v.slice(2,4) + '/' + v.slice(4);
        else if (v.length >= 3) v = v.slice(0,2) + '/' + v.slice(2);
        this.value = v;
    });

    bdateInput.addEventListener('blur', function () {
        const parts = this.value.split('/');
        if (this.value && parts.length === 3) {
            const [mm, dd, yyyy] = parts;
            const d = new Date(`${yyyy}-${mm}-${dd}`);
            if (isNaN(d.getTime())) {
                this.style.borderColor = 'var(--wrong)';
                showToast('Invalid birthdate format.');
            } else {
                this.style.borderColor = '';
            }
        }
    });
}

// ─── Affiliation Label Toggle ────────────────────────────

// ─── Modal Triggers ──────────────────────────────────────
const triggers = [
    { id: 'nav-login-btn', modal: 'modal-login' },
    { id: 'nav-signup-btn', modal: 'modal-signup' },
    { id: 'hero-signup-btn', modal: 'modal-signup' },
    { id: 'hero-login-btn', modal: 'modal-login' },
    { id: 'curriculum-signup-btn', modal: 'modal-signup' },
    { id: 'preview-signup-btn', modal: 'modal-signup' },
    { id: 'go-login', modal: 'modal-login', prevent: true },
    { id: 'go-signup-2', modal: 'modal-signup', prevent: true },
    { id: 'go-forgot', modal: 'modal-forgot', prevent: true },
    { id: 'go-login-2', modal: 'modal-login', prevent: true },
];

triggers.forEach(t => {
    const el = $(t.id);
    if (el) {
        el.addEventListener('click', e => {
            if (t.prevent) e.preventDefault();
            openModal(t.modal);
        });
    }
});

const goBuyVoucher = $('go-buy-voucher');
if (goBuyVoucher) goBuyVoucher.addEventListener('click', e => {
    e.preventDefault();
    openBuyVoucherModal(Number($('voucher-course-select')?.value));
});

const heroBuyVoucher = $('buy-voucher-hero-btn');
if (heroBuyVoucher) heroBuyVoucher.addEventListener('click', () => renderDashboard());

const heroRedeemVoucher = $('redeem-voucher-hero-btn');
if (heroRedeemVoucher) heroRedeemVoucher.addEventListener('click', () => {
    populateCourseSelector();
    openModal('modal-enter-voucher');
});

function updateVoucherButtons() {
    const buyBtn = $('buy-voucher-hero-btn');
    const redeemBtn = $('redeem-voucher-hero-btn');
    
    if (buyBtn) buyBtn.classList.remove('hidden');
    if (redeemBtn) redeemBtn.classList.remove('hidden');
}

// ─── Sign Up ─────────────────────────────────────────────
let pendingRegistrationEmail = '';
const signupBtn = $('signup-btn');
if (signupBtn) {
    signupBtn.addEventListener('click', async () => {
        const fname   = $('su-fname').value.trim();
        const lname   = $('su-lname').value.trim();
        const email   = $('su-email').value.trim().toLowerCase();
        const bdate   = $('su-bdate').value;
        const affName = $('su-affname').value.trim();
        const phone   = $('su-phone').value.trim();
        const country = $('su-country').value;
        const pw      = $('su-password').value;
        const conf    = $('su-confirm').value;

        if (!fname || !lname)              return showToast('First and last name are required.');
        if (!email || !email.includes('@')) return showToast('Enter a valid email address.');
        if (!bdate)                        return showToast('Birthdate is required.');
        if (!affName)                      return showToast('Organization / University name is required.');
        if (!phone)                        return showToast('Contact number is required.');
        if (!country)                      return showToast('Country / pricing region is required.');
        if (pw.length < 8)                 return showToast('Password must be at least 8 characters.');
        if (pw !== conf)                   return showToast('Passwords do not match.');

        try {
            const data = await apiRequest('/api/auth/register', 'POST', {
                'su-fname': fname,
                'su-lname': lname,
                'su-email': email,
                'su-bdate': bdate,
                'su-afftype': 'student',
                'su-affname': affName,
                'su-phone': phone,
                'su-country': country,
                'su-password': pw
            });

            if (data && data.success) {
                pendingRegistrationEmail = data.email;
                if ($('registration-code-email')) $('registration-code-email').textContent = data.email;
                if ($('registration-code')) $('registration-code').value = '';
                openModal('modal-registration-code');
                showToast(data.message, 'success');
            }
        } catch (err) {}
    });
}

const verifyRegistrationBtn = $('verify-registration-btn');
if (verifyRegistrationBtn) verifyRegistrationBtn.addEventListener('click', async () => {
    const code = $('registration-code')?.value.trim() || '';
    if (!/^\d{6}$/.test(code)) return showToast('Enter the complete six-digit verification code.');
    const data = await apiRequest('/api/auth/register/verify','POST',{email:pendingRegistrationEmail,code});
    if (data?.success) { await loginUser(data.user); showToast(data.message,'success'); }
});

const resendRegistrationBtn = $('resend-registration-btn');
if (resendRegistrationBtn) resendRegistrationBtn.addEventListener('click', async () => {
    if (!pendingRegistrationEmail) return;
    const data = await apiRequest('/api/auth/register/resend','POST',{email:pendingRegistrationEmail});
    if (data?.success) showToast(data.message,'success');
});

// ─── Login ───────────────────────────────────────────────
const loginBtn = $('login-btn');
if (loginBtn) {
    loginBtn.addEventListener('click', async () => {
        const emailInput = $('li-email');
        const pwInput    = $('li-password');
        if (!emailInput || !pwInput) return;

        const email = emailInput.value.trim().toLowerCase();
        const pw    = pwInput.value;

        if (!email || !email.includes('@')) return showToast('Enter a valid email address.');
        if (!pw)                            return showToast('Password is required.');

        try {
            const data = await apiRequest('/api/auth/login', 'POST', {
                'email': email,
                'password': pw
            });

            if (data && data.success) {
                loginUser(data.user);
                showToast(data.message, 'success');
            }
        } catch (err) {}
    });
}

// ─── Forgot Password ─────────────────────────────────────
const forgotBtn = $('forgot-btn');
if (forgotBtn) {
    forgotBtn.addEventListener('click', async () => {
        const email = $('fp-email').value.trim().toLowerCase();
        if (!email || !email.includes('@')) return showToast('Enter a valid email address.');

        try {
            const data = await apiRequest('/api/auth/forgot-password', 'POST', { 'email': email });
            if (data && data.success) {
                showToast(data.message, 'info');
            }
        } catch (err) {}
    });
}

// ─── Logout ──────────────────────────────────────────────
const logoutBtn = $('logout-btn');
if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
        try {
            await apiRequest('/api/auth/logout', 'POST');
            state.user = null;
            state.completedTopics = [];
            showScreen('landing-screen');
            showToast('Logged out successfully.', 'info');
        } catch (err) {}
    });
}

// ─── Dynamic Boot initialization ──────────────────────────
async function loadCoursesIfNeeded() {
    if (courses.length > 0) return;
    try {
        const data = await apiRequest('/api/courses');
        if (data && data.success) {
            courses = data.courses;
            state.certificates = Array.isArray(data.certificates) ? data.certificates : [];
        }
    } catch (e) { console.error(e); }
}

async function loadTopicsIfNeeded(courseId) {
    try {
        const topicData = await apiRequest(`/api/courses/${courseId}/topics`);
        if (topicData && topicData.success) {
            topics = topicData.topics;
            subjects = topicData.subjects || [];
            courseMockExamQuestionCount = Number(topicData.mockExamQuestionCount || 0);
            courseMockExamLatestResult = topicData.mockExamLatestResult || null;
            courseMockExamAttemptsUsed = Number(topicData.mockExamAttemptsUsed || 0);
            courseMockExamMaximumAttempts = topicData.mockExamMaximumAttempts === null ? null : Number(topicData.mockExamMaximumAttempts);
            courseMockExamTimeLimitMinutes = topicData.mockExamTimeLimitMinutes === null ? null : Number(topicData.mockExamTimeLimitMinutes);
            courseMockExamPassed = Boolean(topicData.mockExamPassed);
            courseMockExamCertificateAvailable = Boolean(topicData.mockExamCertificateAvailable);
        }
    } catch (e) {
        console.error("Topics catalog loading failed.", e);
    }
}

async function boot() {
    // Fetch public curriculum for the landing page right away
    loadPublicCurriculum();

    // 1. Fetch authenticated session first so guest visits do not trigger protected API errors
    try {
        const sessionData = await apiRequest('/api/auth/session');
        if (sessionData && sessionData.success && sessionData.user) {
            await loadCoursesIfNeeded();
            await loginUser(sessionData.user);
        } else {
            showScreen('landing-screen');
        }
    } catch (e) {
        showScreen('landing-screen');
    }
}

// Start boot pipeline
boot().then(() => {
    checkXenditReturn();
});

async function loadPublicCurriculum() {
    const container = $('dynamic-topic-roadmap');
    if (!container) return;

    try {
        const response = await fetch('/api/public/courses');
        const data = await response.json();

        if (data && data.success) {
            container.innerHTML = '';
            if (data.courses.length === 0) {
                container.innerHTML = '<p style="text-align:center; padding: 2rem; color: var(--text-muted);">Curriculum coming soon.</p>';
                return;
            }

            data.courses.forEach((course, index) => {
                const numStr = (index + 1).toString().padStart(2, '0');
                const item = document.createElement('div');
                item.className = 'roadmap-item';
                item.innerHTML = `
                    <div class="roadmap-number">${numStr}</div>
                    <div class="roadmap-content">
                        <h3>${course.title}</h3>
                        <p>${course.description || 'No description available yet.'}</p>
                    </div>
                `;
                container.appendChild(item);
            });
        }
    } catch (e) {
        container.innerHTML = '<p style="text-align:center; padding: 2rem; color: var(--wrong);">Failed to load curriculum. Please refresh.</p>';
    }
}

async function loginUser(user) {
    if (user && user.role === 'admin') {
        window.location.href = '/admin/dashboard';
        return;
    }

    state.user = user;
    const enrolledFilterKey = `artemis_enrolled_only_${String(user.email || 'learner').toLowerCase()}`;
    state.showEnrolledOnly = localStorage.getItem(enrolledFilterKey) === 'true';
    const layoutKey = `artemis_course_layout_${String(user.email || 'learner').toLowerCase()}`;
    state.courseLayout = localStorage.getItem(layoutKey) === 'grid' ? 'grid' : 'list';
    state.courseUnlocked = user.isCourseUnlocked || false;
    state.isSubscribed = user.isSubscribed || false;
    state.subscriptionExpiresAt = user.subscriptionExpiresAt || null;
    state.hasCertificate = user.hasCertificate || false;

    await loadCoursesIfNeeded();
    
    // Fetch live progress (defaults to all progress)
    try {
        const pData = await apiRequest('/api/progress');
        if (pData && pData.success) {
            state.completedTopics = pData.completedTopics || [];
            state.topicProgressMap = pData.topicProgressMap || {};
            state.hasCertificate = pData.hasCertificate || state.hasCertificate;
            state.hasPassedMidterm = pData.hasPassedMidterm || false;
            if (pData.lastTopicStarted) {
                state.lastTopicStarted = pData.lastTopicStarted;
            }
        }
    } catch (e) {
        state.completedTopics = [];
        state.topicProgressMap = {};
    }


    const heroName = $('dashboard-hero-name');
    if (heroName) heroName.textContent = user.firstName || user.name;
    const certName = $('cert-user-name');
    if (certName) certName.textContent = user.name;

    const panelName = $('panel-name');
    if (panelName) panelName.textContent = user.name || ((user.firstName || '') + ' ' + (user.lastName || '')).trim() || 'Student';
    const panelEmail = $('panel-email');
    if (panelEmail) panelEmail.textContent = user.email || 'N/A';
    const panelOrg = $('panel-org');
    if (panelOrg) panelOrg.textContent = user.affiliationName || user.affName || user.organization || 'Not specified';
    
    updateVoucherButtons();
    closeModal();
    renderDashboard();
    fetchNotifications();
    showScreen('dashboard-screen');
}

// ─── Buy Voucher ─────────────────────────────────────────
function populateCourseSelector(preferredId = null) {
    const select = $('voucher-course-select');
    if (!select) return;
    select.innerHTML = courses.filter(course => !course.is_enrolled)
        .map(course => `<option value="${course.batch_id}">${escapeHtml(course.batch_name)} (${escapeHtml(course.batch_code || course.title)}) — ${escapeHtml(course.title)}</option>`).join('');
    if (preferredId) select.value = String(preferredId);
    populateBatchSelector(Number(select.value), 'voucher');
}

function populateBatchSelector(batchId, context = 'purchase') {
    const course = courses.find(item => Number(item.batch_id) === Number(batchId));
    const select = $(context === 'purchase' ? 'purchase-batch-select' : 'voucher-batch-select');
    const field = $(context === 'purchase' ? 'purchase-batch-field' : 'voucher-batch-field');
    if (!select || !field) return;
    select.innerHTML = course ? `<option value="${course.batch_id}">${escapeHtml(course.batch_name)}${course.batch_code ? ` (${escapeHtml(course.batch_code)})` : ''}</option>` : '';
    field.style.display = context === 'purchase' && course ? 'block' : 'none';
    if (context === 'purchase') selectedPurchaseBatchId = course ? Number(course.batch_id) : null;
}

const voucherCourseSelect = $('voucher-course-select');
if (voucherCourseSelect) voucherCourseSelect.addEventListener('change', () => populateBatchSelector(Number(voucherCourseSelect.value), 'voucher'));
const purchaseBatchSelect = $('purchase-batch-select');
if (purchaseBatchSelect) purchaseBatchSelect.addEventListener('change', () => { selectedPurchaseBatchId = Number(purchaseBatchSelect.value) || null; });

function openBuyVoucherModal(batchId = null) {
    const course = courses.find(item => Number(item.batch_id) === Number(batchId));
    if (!course) {
        showToast('Choose a locked course to subscribe.', 'info');
        closeModal();
        return;
    }
    selectedPurchaseCourseId = course.id;
    populateBatchSelector(course.batch_id, 'purchase');
    if ($('purchase-course-name')) $('purchase-course-name').textContent = `${course.batch_name} — ${course.title}`;
    const price = `${course.currency_symbol || '₱'}${Number(course.display_price ?? 0).toFixed(2)}${course.currency_code === 'USD' ? ' USD' : ''}`;
    const billingPrice = `${course.billing_currency_symbol || '₱'}${Number(course.billing_price ?? 0).toFixed(2)} ${course.billing_currency_code || 'PHP'}`;
    if ($('purchase-course-price')) $('purchase-course-price').textContent = price;
    if ($('purchase-price-summary')) $('purchase-price-summary').textContent = `Price: ${price}`;
    if ($('purchase-billing-price')) $('purchase-billing-price').textContent = billingPrice;
    if ($('purchase-billing-notice')) {
        $('purchase-billing-notice').classList.toggle('hidden', course.currency_code !== 'USD');
    }
    if ($('purchase-access-until')) {
        const endDate = course.batch_ends_at ? new Date(course.batch_ends_at).toLocaleString() : 'the batch access period ends';
        $('purchase-access-until').textContent = `Access available until ${endDate}`;
    }
    const s1 = $('buy-step-1');
    const s2 = $('buy-step-2');
    if (s1) s1.classList.remove('hidden');
    if (s2) s2.classList.add('hidden');
    openModal('modal-buy-voucher');
}

const buyConfirmBtn = $('buy-confirm-btn');
if (buyConfirmBtn) {
    buyConfirmBtn.addEventListener('click', async () => {
        try {
            const data = await apiRequest('/api/voucher/buy', 'POST', { batch_id: selectedPurchaseBatchId });
            if (data && data.success && data.checkout_url) {
                buyConfirmBtn.textContent = 'Redirecting to Xendit...';
                window.location.href = data.checkout_url;
            } else {
                showToast(data.message || 'Failed to initiate purchase', 'error');
            }
        } catch (e) {
            showToast('Network error', 'error');
        }
    });
}

const doneBuyingBtn = $('done-buying-btn');
if (doneBuyingBtn) doneBuyingBtn.addEventListener('click', closeModal);

// ─── Enter Voucher ───────────────────────────────────────
const redeemBtn = $('redeem-voucher-btn');
if (redeemBtn) {
    redeemBtn.addEventListener('click', async () => {
        const input = $('voucher-input');
        if (!input) return;
        const code = input.value.trim();
        if (!code) return showToast('Please enter a subscription code.');

        try {
            const selectedBatchId = Number($('voucher-course-select')?.value || selectedPurchaseBatchId);
            if (!selectedBatchId) return showToast('Please select a batch.');
            const verify = await apiRequest('/api/voucher/verify', 'POST', { 'code': code, batch_id: selectedBatchId });
            if (verify && verify.success) {
                const redeem = await apiRequest('/api/voucher/redeem', 'POST', { 'code': code, batch_id: selectedBatchId });
                if (redeem && redeem.success) {
                    closeModal();
                    state.voucherCode = code;
                    localStorage.setItem('cssm_voucher', code);
                    
                    const enrolled = courses.find(course => Number(course.batch_id) === Number(redeem.batchId));
                    if (enrolled) enrolled.is_enrolled = true;
                    updateVoucherButtons();
                    renderDashboard();
                    
                    showToast(redeem.message || 'Subscription accepted! Your review access is now active.', 'success');
                }
            }
        } catch (e) {}
    });
}

function fadeTransition(elementsToHide, elementsToShow, showDisplays) {
    const allHiding = elementsToHide.filter(e => e && e.style.display !== 'none');
    allHiding.forEach(el => {
        el.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
        el.style.opacity = '0';
        el.style.transform = 'translateY(10px)';
    });
    setTimeout(() => {
        allHiding.forEach(el => el.style.display = 'none');
        elementsToShow.forEach((el, idx) => {
            if (!el) return;
            el.style.transition = 'none';
            el.style.opacity = '0';
            el.style.transform = 'translateY(10px)';
            el.style.display = showDisplays[idx] || '';
        });
        void document.body.offsetHeight; // force reflow
        elementsToShow.forEach(el => {
            if (!el) return;
            el.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        });
    }, allHiding.length ? 200 : 0);
}

// ─── Dashboard ───────────────────────────────────────────
function renderDashboard() {
    // Reset view to courses menu
    const cdArea = $('course-details-area');
    const dcHead = $('dashboard-courses-head');
    const cCont = $('courses-container');
    if (cdArea) { cdArea.style.display = 'none'; cdArea.style.opacity = '0'; }
    if (dcHead) { dcHead.style.display = ''; dcHead.style.opacity = '1'; dcHead.style.transform = 'none'; }
    if (cCont) { cCont.style.display = ''; cCont.style.opacity = '1'; cCont.style.transform = 'none'; }

    const resumeBtn = $('resume-module-btn');
    if (resumeBtn) resumeBtn.classList.add('hidden');
    const exploreBtn = $('explore-courses-btn');
    if (exploreBtn) exploreBtn.classList.remove('hidden');

    const m1Label = $('metric-1-label');
    const m1Val = $('dashboard-progress-summary');
    const m2Label = $('metric-2-label');
    const m2Val = $('dashboard-modules-completed');
    if (m1Label) m1Label.textContent = 'Certificates';
    if (m1Val) m1Val.textContent = state.certificates ? String(state.certificates.length) : '0';
    if (m2Label) m2Label.textContent = 'Batches Available';
    if (m2Val) m2Val.textContent = String(courses.length);

    const cContainer = $('courses-container');
    if (cContainer) {
        cContainer.innerHTML = '';
        const visibleCourses = state.showEnrolledOnly
            ? courses.filter(course => course.is_enrolled)
            : courses;

        if (visibleCourses.length === 0) {
            cContainer.innerHTML = `<div class="empty-course-filter"><i data-lucide="book-open"></i><p>No enrolled batches yet.</p><span>Turn off “Enrolled batches only” to browse available batches.</span></div>`;
        }

        visibleCourses.forEach(course => {
            const card = document.createElement('div');
            const isLocked = !course.is_enrolled;
            card.className = `topic-card ${isLocked ? 'course-locked' : ''}`.trim();
            card.style.cursor = 'pointer';

            let lockMsg = '';
            if (isLocked) {
                lockMsg = `
                    <div class="course-card-actions">
                        <button type="button" class="btn-primary course-subscribe-btn">Enroll — ${course.currency_symbol || '₱'}${Number(course.display_price ?? 0).toFixed(2)}${course.currency_code === 'USD' ? ' USD' : ''}</button>
                        <button type="button" class="btn-ghost course-code-btn">Use Code</button>
                    </div>`;
            }
            const rankingLabel = course.is_enrolled
                ? (course.mock_exam_rank
                    ? `<div class="course-ranking-badge">Rank: <strong>${course.mock_exam_rank} out of ${course.mock_exam_ranked_count}</strong></div>`
                    : '<div class="course-ranking-badge unranked">Rank: <strong>--</strong></div>')
                : '';
            const certificateLabel = course.has_certificate
                ? '<div class="course-certificate-badge">Certificate Earned</div>'
                : '';
            const formatCourseDate = value => value
                ? new Date(value).toLocaleDateString('en-US', {month:'long', day:'numeric', year:'numeric'})
                : null;

            card.innerHTML = `
                <p class="topic-num">Batch ${escapeHtml(course.batch_code || '')}</p>
                <h3>${escapeHtml(course.batch_name)}</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem;"><strong>Assigned course:</strong> ${escapeHtml(course.title)}</p>
                <p style="color: var(--text-muted); font-size: 0.9rem;">${escapeHtml(course.batch_description || course.description || '')}</p>
                <div class="course-availability-dates">
                    <p><span>Start date:</span> ${formatCourseDate(course.batch_starts_at) || 'To be announced'}</p>
                    <p><span>End date:</span> ${formatCourseDate(course.batch_ends_at) || 'No end date'}</p>
                </div>
                ${rankingLabel}
                ${certificateLabel}
                ${lockMsg}
            `;
            const subscribeBtn = card.querySelector('.course-subscribe-btn');
            if (subscribeBtn) subscribeBtn.addEventListener('click', event => {
                event.stopPropagation();
                openBuyVoucherModal(course.batch_id);
            });
            const codeBtn = card.querySelector('.course-code-btn');
            if (codeBtn) codeBtn.addEventListener('click', event => {
                event.stopPropagation();
                selectedPurchaseCourseId = course.id;
                populateCourseSelector(course.batch_id);
                openModal('modal-enter-voucher');
            });
            card.addEventListener('click', async () => {
                if (isLocked) {
                    openBuyVoucherModal(course.batch_id);
                    return;
                }
                currentCourseId = course.id;
                currentBatchId = course.batch_id;
                localStorage.setItem('last_course_id', course.id);
                const titleEl = $('course-details-title');
                if (titleEl) titleEl.textContent = course.title;
                fadeTransition(
                    [$('dashboard-courses-head'), cContainer],
                    [$('course-details-area')],
                    ['block']
                );
                
                await loadTopicsIfNeeded(course.id);
                // Also fetch progress for this specific course
                const pData = await apiRequest('/api/progress?course_id=' + course.id);
                if (pData && pData.success) {
                    state.completedTopics = pData.completedTopics || [];
                    state.topicProgressMap = pData.topicProgressMap || {};
                    state.hasPassedMidterm = pData.hasPassedMidterm || false;
                }
                
                renderSubjects();
            });
            cContainer.appendChild(card);
        });
        if (window.lucide) lucide.createIcons();
        applyLayoutMode(state.courseLayout, false);

        const enrolledToggle = $('enrolled-only-toggle');
        if (enrolledToggle) {
            enrolledToggle.checked = state.showEnrolledOnly;
            enrolledToggle.onchange = () => {
                state.showEnrolledOnly = enrolledToggle.checked;
                const key = `artemis_enrolled_only_${String(state.user?.email || 'learner').toLowerCase()}`;
                localStorage.setItem(key, String(state.showEnrolledOnly));
                renderDashboard();
            };
        }
        
        const backBtn = $('back-to-courses-btn');
        if (backBtn) {
            backBtn.onclick = () => {
                fadeTransition(
                    [$('course-details-area')],
                    [$('dashboard-courses-head'), cContainer],
                    ['', '']
                );
                currentSubjectId = null;
                const backToSubjects = $('back-to-subjects-btn');
                if (backToSubjects) backToSubjects.classList.add('hidden');
                const resumeBtn = $('resume-module-btn');
                if (resumeBtn) resumeBtn.classList.add('hidden');
                const exploreBtn = $('explore-courses-btn');
                if (exploreBtn) exploreBtn.classList.remove('hidden');

                const m1Label = $('metric-1-label');
                const m1Val = $('dashboard-progress-summary');
                const m2Label = $('metric-2-label');
                const m2Val = $('dashboard-modules-completed');
                if (m1Label) m1Label.textContent = 'Certificates';
                if (m1Val) m1Val.textContent = state.certificates ? String(state.certificates.length) : '0';
                if (m2Label) m2Label.textContent = 'Courses Available';
                if (m2Val) m2Val.textContent = String(courses.length);
            };
        }
    }
}

function getSubtopicLearningItemCount(subtopic) {
    if (subtopic.contentType && subtopic.contentType !== 'subtopic') return 1;
    let count = 0;
    if (subtopic.documentationPath) count++;
    if (subtopic.videoUrl || subtopic.videoUploadUrl) count++;
    return count || 1;
}

function getTopicFlatLearningItems(topic) {
    const items = [];
    (topic.subtopics || []).forEach((sub, subIndex) => {
        if (sub.contentType === 'mock_exam') return;
        if (sub.contentType === 'zoom_link') {
            items.push({sub, subIndex, type: 'zoom'});
            return;
        }
        if (sub.contentType && sub.contentType !== 'subtopic') {
            items.push({sub, subIndex, type: 'assessment'});
            return;
        }
        if (sub.documentationPath) items.push({sub, subIndex, type: 'doc'});
        if (sub.videoUrl || sub.videoUploadUrl) items.push({sub, subIndex, type: 'video'});
        if (!sub.documentationPath && !sub.videoUrl && !sub.videoUploadUrl) items.push({sub, subIndex, type: 'none'});
    });
    return items;
}

function hasCompletedSubjectPreTest(subjectId) {
    return topics
        .filter(topic => Number(topic.subjectId) === Number(subjectId))
        .some(topic => (topic.subtopics || []).some(subtopic =>
            subtopic.contentType === 'pre_test' && Number(subtopic.attemptsUsed || 0) > 0
        ));
}

function hasCompletedSubjectPolicy(subjectId) {
    const policyTopic = topics.find(topic =>
        Number(topic.subjectId) === Number(subjectId) &&
        (topic.isPolicyTopic === true || /policy/i.test(String(topic.title || '')))
    );
    if (!policyTopic) return false;
    const unlockedIndex = Number(state.topicProgressMap[policyTopic.id] || 0);
    const requiredItems = getTopicFlatLearningItems(policyTopic)
        .map((item, flatIndex) => ({item, flatIndex}))
        .filter(({item}) => item.type !== 'none' && item.sub.contentType !== 'pre_test');
    return requiredItems.length > 0 && requiredItems.every(({item, flatIndex}) => {
        const hasSavedScore = item.type !== 'assessment' || Number(item.sub.attemptsUsed || 0) > 0;
        return flatIndex < unlockedIndex && hasSavedScore;
    });
}

function isSubjectMockExamUnlocked(subjectId) {
    const requiredItems = [];
    topics
        .filter(topic => Number(topic.subjectId) === Number(subjectId))
        .forEach(topic => {
            const unlockedIndex = Number(state.topicProgressMap[topic.id] || 0);
            getTopicFlatLearningItems(topic).forEach((item, flatIndex) => {
                if (item.sub.contentType === 'mock_exam' || item.type === 'none') return;
                const hasSavedScore = item.type !== 'assessment' || Number(item.sub.attemptsUsed || 0) > 0;
                requiredItems.push(flatIndex < unlockedIndex && hasSavedScore);
            });
        });
    return requiredItems.length > 0 && requiredItems.every(Boolean);
}

function isTopicContentComplete(topic) {
    const unlockedIndex = Number(state.topicProgressMap[topic.id] || 0);
    const requiredItems = getTopicFlatLearningItems(topic)
        .map((item, flatIndex) => ({item, flatIndex}))
        .filter(({item}) => item.type !== 'none');
    return requiredItems.length > 0 && requiredItems.every(({item, flatIndex}) => {
        const hasSavedScore = item.type !== 'assessment' || Number(item.sub.attemptsUsed || 0) > 0;
        return flatIndex < unlockedIndex && hasSavedScore;
    });
}

function isLearningItemAccessible(topic, item, flatIndex) {
    const preTestCompleted = hasCompletedSubjectPreTest(topic.subjectId);
    const isPolicyTopic = topic.isPolicyTopic === true || /policy/i.test(String(topic.title || ''));
    const isPreTestTopic = (topic.subtopics || []).some(subtopic => subtopic.contentType === 'pre_test');
    // The Policy topic remains a normal sequential lesson before the gate is met:
    // its readings/videos open first, followed by its Pre-test. Other topics stay locked.
    if (!preTestCompleted) {
        const policyCompleted = hasCompletedSubjectPolicy(topic.subjectId);
        return (isPolicyTopic || (isPreTestTopic && policyCompleted)) &&
            flatIndex <= Number(window.currentUnlockedIdx || 0);
    }
    if (item.sub.contentType === 'mock_exam') return isSubjectMockExamUnlocked(topic.subjectId);
    return flatIndex <= Number(window.currentUnlockedIdx || 0);
}

function getSubjectLearningProgress(subjectId) {
    let totalContents = 0;
    let completedContents = 0;

    topics
        .filter(topic => Number(topic.subjectId) === Number(subjectId))
        .forEach(topic => {
            let topicContentIndex = 0;
            const unlockedIndex = Number(state.topicProgressMap[topic.id] || 0);
            (topic.subtopics || []).forEach(subtopic => {
                if (subtopic.contentType === 'mock_exam') return;
                const itemCount = getSubtopicLearningItemCount(subtopic);
                for (let offset = 0; offset < itemCount; offset++) {
                    const isAssessment = subtopic.contentType && !['subtopic', 'zoom_link'].includes(subtopic.contentType);
                    const hasSavedScore = Number(subtopic.attemptsUsed || 0) > 0;
                    if (topicContentIndex < unlockedIndex && (!isAssessment || hasSavedScore)) {
                        completedContents++;
                    }
                    topicContentIndex++;
                }
            });
            totalContents += topicContentIndex;
        });

    return {
        completed: completedContents,
        total: totalContents,
        percentage: totalContents > 0 ? Math.round((completedContents / totalContents) * 100) : 0,
    };
}

function renderSubjects() {
    const container = $('subjects-container');
    const listArea = $('subjects-list-area');
    const topicsArea = $('subject-topics-area');
    if (!container) return;
    const resumeBtn = $('resume-module-btn');
    if (resumeBtn) resumeBtn.classList.add('hidden');
    if (listArea) listArea.style.display = '';
    if (topicsArea) topicsArea.style.display = 'none';
    const progressArea = $('course-progress-report-area');
    if (progressArea) progressArea.style.display = 'none';
    $('course-subjects-tab')?.classList.add('active');
    $('course-progress-tab')?.classList.remove('active');
    const backToSubjects = $('back-to-subjects-btn');
    if (backToSubjects) backToSubjects.classList.add('hidden');
    container.innerHTML = '';
    if (!subjects.length) {
        container.innerHTML = '<div class="empty-course-filter"><p>No approved subjects are available in this course yet.</p></div>';
        return;
    }
    subjects.forEach(subject => {
        const learningProgress = getSubjectLearningProgress(subject.id);
        const row = document.createElement('div');
        row.className = 'learner-subject-row';
        row.innerHTML = `
            <div class="learner-subject-code">${subject.code || 'SUBJECT'}</div>
            <div class="learner-subject-name"><strong>${subject.title}</strong><span>${subject.description || `${subject.topicCount} topic${subject.topicCount === 1 ? '' : 's'}`}</span></div>
            <div class="learner-subject-progress"><div class="learner-subject-progress-meta"><span>Progress (${learningProgress.completed}/${learningProgress.total} contents)</span><strong>${learningProgress.percentage}%</strong></div><div class="learner-subject-progress-track"><span style="width:${learningProgress.percentage}%"></span></div></div>
            <div class="learner-subject-action"><button type="button" class="btn-primary">Open</button></div>`;
        row.querySelector('button').addEventListener('click', () => {
            currentSubjectId = Number(subject.id);
            if (listArea) listArea.style.display = 'none';
            if (topicsArea) topicsArea.style.display = '';
            if (backToSubjects) backToSubjects.classList.remove('hidden');
            const title = $('subject-details-title');
            if (title) title.textContent = `${subject.code || ''} ${subject.title}`.trim();
            renderTopics();
        });
        container.appendChild(row);
    });
    const mockContainer = $('course-mock-exams-container');
    if (mockContainer) {
        const allSubjectsComplete = subjects.length > 0 && subjects.every(subject =>
            getSubjectLearningProgress(subject.id).percentage === 100
        );
        mockContainer.innerHTML = '';
        if (courseMockExamQuestionCount > 0) {
            const attemptsExhausted = courseMockExamMaximumAttempts !== null && courseMockExamAttemptsUsed >= courseMockExamMaximumAttempts;
            const retakeBlocked = courseMockExamPassed || attemptsExhausted;
            const canStart = allSubjectsComplete && !retakeBlocked;
            const attemptLabel = courseMockExamMaximumAttempts === null
                ? `${courseMockExamAttemptsUsed} attempt${courseMockExamAttemptsUsed === 1 ? '' : 's'} used (Unlimited)`
                : `${courseMockExamAttemptsUsed} of ${courseMockExamMaximumAttempts} attempt${courseMockExamMaximumAttempts === 1 ? '' : 's'} used`;
            const statusLabel = courseMockExamPassed ? 'Passed — certificate available'
                : (attemptsExhausted ? 'Maximum attempts reached' : (allSubjectsComplete ? 'Available' : 'Complete all subjects to unlock'));
            const mockResult = courseMockExamLatestResult
                ? `${Number(courseMockExamLatestResult.score).toLocaleString(undefined,{maximumFractionDigits:2})}/${Number(courseMockExamLatestResult.total).toLocaleString(undefined,{maximumFractionDigits:2})}`
                : 'Not taken';
            const card = document.createElement('div');
            card.className = `course-mock-exam-card ${courseMockExamPassed ? 'passed' : (canStart ? 'available' : 'locked')}`;
            card.innerHTML = `
                <div><span class="course-mock-exam-label">Mock Exam</span><h3>Comprehensive Mock Exam</h3><p>${courseMockExamQuestionCount} approved question${courseMockExamQuestionCount === 1 ? '' : 's'} · ${attemptLabel} · ${courseMockExamTimeLimitMinutes ? `${courseMockExamTimeLimitMinutes} minutes` : 'No time limit'}</p><p class="course-mock-result">Latest result: <strong>${mockResult}</strong></p></div>
                <div class="course-mock-exam-action"><span>${statusLabel}</span><div class="course-mock-exam-buttons"><button type="button" class="btn-primary mock-exam-start" ${canStart ? '' : 'disabled'}>${courseMockExamPassed ? 'Passed' : (attemptsExhausted ? 'No Attempts Left' : (allSubjectsComplete ? 'Start Mock Exam' : 'Locked'))}</button>${retakeBlocked && courseMockExamAttemptsUsed > 0 ? '<button type="button" class="btn-ghost mock-exam-summary">View Summary</button>' : ''}${courseMockExamPassed && courseMockExamCertificateAvailable ? '<button type="button" class="btn-ghost mock-exam-certificate">View Certificate</button>' : ''}</div></div>`;
            card.querySelector('.mock-exam-start').onclick = async () => {
                if (!canStart) return;
                if ($('mock-instruction-question-count')) $('mock-instruction-question-count').textContent = courseMockExamQuestionCount;
                openModal('modal-mock-exam-instructions');
            };
            const certificateButton = card.querySelector('.mock-exam-certificate');
            if (certificateButton) certificateButton.onclick = async () => {
                const result = await apiRequest(`/api/courses/${currentCourseId}/certificate`);
                if (result?.success && result.certificate) showCertificate(result.certificate);
            };
            const summaryButton = card.querySelector('.mock-exam-summary');
            if (summaryButton) summaryButton.onclick = async () => {
                const result = await apiRequest(`/api/courses/${currentCourseId}/exam/summary`);
                if (result?.success) showAssessmentSummary(result, () => { renderSubjects(); showScreen('dashboard-screen'); });
            };
            mockContainer.appendChild(card);
        }
    }
    const back = $('back-to-subjects-btn');
    if (back) back.onclick = () => { currentSubjectId = null; renderSubjects(); };
}

const confirmStartMockExam = $('confirm-start-mock-exam');
if (confirmStartMockExam) confirmStartMockExam.addEventListener('click', async () => {
    confirmStartMockExam.disabled = true;
    try {
        const result = await apiRequest(`/api/courses/${currentCourseId}/exam/questions?type=final`);
        if (!result?.success) return;
        closeModal();
        state.examType = 'final';
        startQuiz(result.questions, {timeLimitMinutes: result.timeLimitMinutes});
    } finally {
        confirmStartMockExam.disabled = false;
    }
});

function renderTopics() {
    const container = $('topics-container');
    if (!container) return;
    container.innerHTML = '';

    const topicCountEl = $('dashboard-topic-count');
    if (topicCountEl) topicCountEl.textContent = String(topics.filter(topic => Number(topic.subjectId) === Number(currentSubjectId)).length);

    const subjectTopics = topics.map((topic, originalIndex) => ({topic, originalIndex})).filter(item => Number(item.topic.subjectId) === Number(currentSubjectId));
    subjectTopics.forEach(({topic, originalIndex}, index) => {
        const done = isTopicContentComplete(topic);
        const preTestCompleted = hasCompletedSubjectPreTest(topic.subjectId);
        const isPolicyTopic = topic.isPolicyTopic === true || /policy/i.test(String(topic.title || ''));
        const isPreTestTopic = (topic.subtopics || []).some(subtopic => subtopic.contentType === 'pre_test');
        const policyCompleted = hasCompletedSubjectPolicy(topic.subjectId);
        const unlocked = isPolicyTopic || preTestCompleted || (isPreTestTopic && policyCompleted);
        let lockMsg = '';
        
        if (!unlocked) {
            lockMsg = `<span class="topic-lock"><i data-lucide="lock"></i>${isPreTestTopic ? 'Complete the Policy topic to unlock' : 'Complete the Pre-test to unlock'}</span>`;
        }

        const card = document.createElement('div');
        card.className = `topic-card ${done ? 'completed' : ''} ${(unlocked || done) ? '' : 'locked'}`.trim();
        const assessmentResults = (topic.subtopics || [])
            .filter(subtopic => subtopic.contentType && !['subtopic', 'zoom_link', 'mock_exam'].includes(subtopic.contentType))
            .map(subtopic => {
                const labels = {pre_test:'Pre-test',post_test:'Post-test',practice_test:'Practice Test',mock_exam:'Mock Exam'};
                const scoreAvailable = subtopic.latestScore !== null && subtopic.latestScore !== undefined &&
                    subtopic.latestTotal !== null && subtopic.latestTotal !== undefined;
                const score = scoreAvailable ? Number(subtopic.latestScore).toLocaleString(undefined, {maximumFractionDigits: 2}) : null;
                const total = scoreAvailable ? Number(subtopic.latestTotal).toLocaleString(undefined, {maximumFractionDigits: 2}) : null;
                const percentage = scoreAvailable && Number(subtopic.latestTotal) > 0
                    ? Math.round((Number(subtopic.latestScore) / Number(subtopic.latestTotal)) * 100)
                    : null;
                const tries = Number(subtopic.attemptsUsed || 0);
                const attemptLabel = `${tries} ${tries === 1 ? 'Attempt' : 'Attempts'}`;
                const resultClass = scoreAvailable ? (subtopic.latestPassed ? 'passed' : 'scored') : 'not-taken';
                return `<div class="topic-assessment-result"><span>${labels[subtopic.contentType] || subtopic.title || 'Assessment'}:</span><strong class="${resultClass}">${attemptLabel} | ${scoreAvailable ? `${score} out of ${total} — ${percentage}%` : '--'}</strong></div>`;
            }).join('');

        card.innerHTML = `
            <p class="topic-num">Topic ${topic.sort_order}</p>
            <h3>${topic.title}${done ? '<span class="topic-done-badge"><i data-lucide="check"></i></span>' : ''}</h3>
            ${assessmentResults ? `<div class="topic-assessment-results">${assessmentResults}</div>` : ''}
            ${(unlocked || done) ? '' : lockMsg}
        `;
        card.style.cursor = 'pointer';
        card.addEventListener('click', () => {
            if (unlocked) {
                openTopic(originalIndex);
            } else {
                showToast('Complete the Pre-test to unlock the other topics.', 'info');
            }
        });
        container.appendChild(card);
    });

    const completedInSubject = subjectTopics.filter(item => state.completedTopics.includes(item.topic.id)).length;
    const pct = subjectTopics.length > 0 ? Math.round((completedInSubject / subjectTopics.length) * 100) : 0;

    const exploreBtn = $('explore-courses-btn');
    if (exploreBtn) exploreBtn.classList.add('hidden');

    const m1Label = $('metric-1-label');
    if (m1Label) m1Label.textContent = 'Progress';
    const summaryEl = $('dashboard-progress-summary');
    if (summaryEl) summaryEl.textContent = `${pct}%`;

    const m2Label = $('metric-2-label');
    if (m2Label) m2Label.textContent = 'Modules Completed';
    const completedEl = $('dashboard-modules-completed');
    if (completedEl) completedEl.textContent = `${completedInSubject} / ${subjectTopics.length}`;

    const resumeBtn = $('resume-module-btn');
    if (resumeBtn) {
        if (state.hasCertificate) {
            resumeBtn.textContent = 'View Your Certificate';
            resumeBtn.classList.remove('hidden');
        } else if (state.completedTopics.length === 0 && !state.lastTopicStarted) {
            resumeBtn.textContent = 'Start Your First Lesson';
            resumeBtn.classList.remove('hidden');
        } else {
            resumeBtn.textContent = 'Resume Module';
            resumeBtn.classList.remove('hidden');
        }
    }

    if (window.lucide) lucide.createIcons();
}

function updateMidtermCard() {
    const btn = $('midterm-exam-btn');
    if (!btn) return;

    const midpoint = Math.ceil(topics.length / 2);
    const firstHalfTopicIds = topics.slice(0, midpoint).map(topic => topic.id);
    const isUnlocked = midpoint > 0 && firstHalfTopicIds.every(id => state.completedTopics.includes(id));

    if (state.hasPassedMidterm) {
        btn.className = 'topic-card completed final-exam-card';
        btn.innerHTML = `
            <p class="topic-num">Midterm Exam</p>
            <h3>Course Midterm Exam<span class="topic-done-badge"><i data-lucide="check"></i></span></h3>
            <span>You passed the instructor-defined midterm exam.</span>
            <button class="btn-ghost" style="margin-top:1rem;">Retake Midterm</button>
        `;
    } else if (!isUnlocked) {
        btn.className = 'topic-card locked final-exam-card';
        btn.innerHTML = `
            <p class="topic-num">Midterm Exam</p>
            <h3>Course Midterm Exam</h3>
            <span class="topic-lock"><i data-lucide="lock"></i>Complete the first ${midpoint} topic${midpoint === 1 ? '' : 's'} to unlock</span>
        `;
        btn.onclick = null;
        if (window.lucide) lucide.createIcons({ root: btn });
        return;
    } else {
        btn.className = 'topic-card final-exam-card';
        btn.innerHTML = `
            <p class="topic-num">Midterm Exam</p>
            <h3>Course Midterm Exam</h3>
            <span>Ready to take the instructor-defined midterm.</span>
            <button class="btn-primary" style="margin-top:1rem; width:100%;">Start Midterm</button>
        `;
    }

    btn.onclick = async () => {
        state.examType = 'mid';
        const exam = await apiRequest('/api/courses/' + currentCourseId + '/exam/questions?type=mid');
        if (exam && exam.success) startQuiz(exam.questions, {timeLimitMinutes: exam.timeLimitMinutes});
    };
    if (window.lucide) lucide.createIcons({ root: btn });
}

const resumeCourseBtn = $('resume-module-btn');
if (resumeCourseBtn) {
    resumeCourseBtn.addEventListener('click', async () => {
        if (state.hasCertificate) {
            try {
                const res = await apiRequest('/api/courses/' + currentCourseId + '/certificate');
                if (res && res.success && res.certificate) {
                    showCertificate(res.certificate);
                }
            } catch(e) {}
            return;
        }
        let nextIndex = 0;
        if (state.lastTopicStarted) {
            nextIndex = topics.findIndex(t => t.id == state.lastTopicStarted);
            if (nextIndex < 0) nextIndex = 0;
        } else {
            nextIndex = topics.findIndex((topic, index) => {
                const prevTopicId = index > 0 ? topics[index - 1].id : null;
                return index === 0 || (prevTopicId && state.completedTopics.includes(prevTopicId));
            });
        }
            if (nextIndex >= 0) {
                openTopic(nextIndex);
            }
    });
}

function updateFinalCard() {
    const btn = $('final-exam-btn');
    if (!btn) return;
    const allDone = topics.length > 0 && state.completedTopics.length === topics.length;

    const statusEl = $('final-card-status');
    const lockEl = $('final-card-lock');
    const h3El = btn.querySelector('h3');

    if (state.hasCertificate) {
        btn.className = 'topic-card completed final-exam-card';
        btn.style.borderColor = '';
        btn.style.boxShadow = '';
        btn.innerHTML = `
            <p class="topic-num">Mock Exam</p>
            <h3 style="margin-bottom: 0.5rem; font-size: 1.25rem; color: var(--text);">Final Course Examination<span class="topic-done-badge"><i data-lucide="check"></i></span></h3>
            <span id="final-card-status" style="display: block; margin-bottom: 1.5rem; color: var(--text-muted); font-size: 0.9rem;">You have successfully passed the Mock Exam. Congratulations!</span>
            <div style="display: flex; gap: 0.75rem;">
                <button id="view-cert-btn" class="btn-primary" style="flex: 1; font-size: 0.9rem; padding: 0.6rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">View Certificate</button>
                <button id="retake-exam-btn" class="btn-ghost" style="flex: 1; font-size: 0.9rem; padding: 0.6rem; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; gap: 0.5rem;">Retake</button>
            </div>
        `;
        
        $('view-cert-btn').onclick = async (e) => {
            e.stopPropagation();
            try {
                const res = await apiRequest('/api/courses/' + currentCourseId + '/certificate');
                if (res && res.success && res.certificate) {
                    showCertificate(res.certificate);
                }
            } catch(e) {}
        };
        
        $('retake-exam-btn').onclick = async (e) => {
            e.stopPropagation();
            state.examType = 'final';
            try {
                const exam = await apiRequest('/api/courses/' + currentCourseId + '/exam/questions?type=final');
                if (exam && exam.success) {
                    finalExam = exam.questions;
                    startQuiz(finalExam, {timeLimitMinutes: exam.timeLimitMinutes});
                }
            } catch(e) {}
        };
        
        btn.onclick = null;
        btn.onclick = null;
    } else if (!allDone) {
        btn.className = 'topic-card locked final-exam-card';
        btn.style.borderColor = 'var(--border)';
        btn.style.boxShadow = 'none';
        btn.innerHTML = `
            <p class="topic-num" style="color: var(--text-muted); font-weight: bold;">Mock Exam</p>
            <h3 style="color: var(--text-muted);">Final Course Examination</h3>
            <span style="display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.9rem;">Comprehensive test covering all topics!</span>
            <span class="topic-lock"><i data-lucide="lock"></i>Complete all topics to unlock</span>
        `;
        btn.onclick = null;
    } else {
        btn.className = 'topic-card final-exam-card';
        btn.style.borderColor = 'var(--accent)';
        btn.style.boxShadow = '0 4px 12px rgba(99, 102, 241, 0.15)';
        btn.innerHTML = `
            <div class="exam-card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <p class="topic-num" style="margin: 0; font-weight: 600; color: var(--accent);">Mock Exam</p>
                <span class="topic-unlock" style="background: rgba(99, 102, 241, 0.1); color: var(--accent); padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; display: flex; align-items: center; gap: 0.35rem; font-weight: 600;"><i data-lucide="unlock" style="width: 14px; height: 14px;"></i> Unlocked</span>
            </div>
            <h3 style="margin-bottom: 0.5rem; font-size: 1.25rem; color: var(--text);">Final Course Examination</h3>
            <span id="final-card-status" style="display: block; margin-bottom: 1.5rem; color: var(--text-muted); font-size: 0.9rem;">Ready to take the Mock Exam. Good luck!</span>
            <button class="btn-primary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.95rem; padding: 0.75rem;">
                <i data-lucide="play-circle" style="width: 18px; height: 18px;"></i> Start Exam
            </button>
        `;
        btn.onclick = async () => {
            const activeCourse = courses.find(course => Number(course.id) === Number(currentCourseId));
            if (activeCourse?.is_enrolled) {
                state.examType = 'final';
                try {
                    const exam = await apiRequest('/api/courses/' + currentCourseId + '/exam/questions?type=final');
                    if (exam && exam.success) {
                        finalExam = exam.questions;
                        startQuiz(finalExam, {timeLimitMinutes: exam.timeLimitMinutes});
                    }
                } catch(e) {}
            } else {
                selectedPurchaseCourseId = currentCourseId;
                populateCourseSelector(currentCourseId);
                openModal('modal-enter-voucher');
            }
        };
    }

    if (typeof lucide !== 'undefined') lucide.createIcons({ root: btn });
}

// ─── Artemis review learning environment ─────────────────────
let currentSubtopicIndex = 0;
let activeSubtopicAssessmentId = null;
let activeSubtopicAssessmentContext = 'topic';

function openTopic(index) {
    state.currentTopicIndex = index;
    currentSubtopicIndex = 0;

    if (topics[index]) {
        apiRequest('/api/progress/start', 'POST', { topic_id: topics[index].id })
            .then(res => {
                if (res && res.success && res.lastTopicStarted) {
                    state.lastTopicStarted = res.lastTopicStarted;
                }
            }).catch(() => {});
    }

    renderLesson();
    showScreen('lesson-screen');
}

function renderLesson(openIdx = 0) {
    const topic = topics[state.currentTopicIndex];
    if (!topic) return;

    // Determine current unlocked progress for this topic from backend
    let maxUnlocked = state.topicProgressMap[topic.id] || 0;
    window.currentUnlockedIdx = maxUnlocked;
    window.currentTopicStorageKey = `certApp_progress_${topic.id}`; // legacy fallback if needed

    // Set topic title in sidebar
    const titleEl = $('current-topic-title');
    if (titleEl) titleEl.textContent = topic.title;

    const navList = $('subtopics-nav-list');
    if (!navList) return;
    navList.innerHTML = '';

    const subtopics = topic.subtopics || [];
    const noSubMsg  = $('no-subtopics-msg');
    const subHeader = $('subtopic-header');
    const videoWrap = $('video-container');
    const docsWrap  = $('docs-container');
    const assessmentWrap = $('subtopic-assessment-container');
    if (assessmentWrap) assessmentWrap.style.display = 'none';

    if (subtopics.length === 0) {
        // No subtopics — show placeholder
        if (noSubMsg)  noSubMsg.style.display  = 'flex';
        if (subHeader) subHeader.style.display = 'none';
        if (videoWrap) videoWrap.style.display = 'none';
        if (docsWrap)  docsWrap.style.display  = 'none';

        // Legacy fallback: show topic-level video/docs if present
        if (topic.videoUrl || (topic.videos && topic.videos.length) || topic.documentationPath) {
            if (noSubMsg) noSubMsg.style.display = 'none';
            if (subHeader) {
                subHeader.style.display = 'flex';
                const numEl = $('subtopic-header-num');
                const ttlEl = $('subtopic-header-title');
                if (numEl) numEl.textContent = 'Topic Content';
                if (ttlEl) ttlEl.textContent = topic.title;
            }
            loadVideoForSubtopic({ videoUrl: topic.videoUrl });
            loadDocsForSubtopic({ documentationPath: topic.documentationPath, documentationFilename: topic.documentationFilename, documentationType: topic.documentationType });
            
            if (videoWrap) videoWrap.style.display = 'block';
            if (docsWrap)  docsWrap.style.display  = 'flex';
        }
        return;
    }

    if (noSubMsg)  noSubMsg.style.display  = 'none';
    if (subHeader) subHeader.style.display = 'flex';

    // Build flattened items list for openFlattenedItem()
    window.currentFlattenedItems = [];
    subtopics.forEach((sub, subIndex) => {
        if (sub.contentType === 'mock_exam') return;
        if (sub.contentType === 'zoom_link') {
            window.currentFlattenedItems.push({ sub, type: 'zoom', subIndex });
            return;
        }
        if (sub.contentType && sub.contentType !== 'subtopic') {
            window.currentFlattenedItems.push({ sub, type: 'assessment', subIndex });
            return;
        }
        if (sub.documentationPath) {
            window.currentFlattenedItems.push({ sub, type: 'doc',   subIndex });
        }
        if (sub.videoUrl || sub.videoUploadUrl) {
            window.currentFlattenedItems.push({ sub, type: 'video', subIndex });
        }
        if (!sub.videoUrl && !sub.videoUploadUrl && !sub.documentationPath) {
            window.currentFlattenedItems.push({ sub, type: 'none',  subIndex });
        }
    });

    // Build GROUPED sidebar: subtopic title as header, items nested under it
    subtopics.forEach((sub, subIndex) => {
        if (sub.contentType === 'mock_exam') return;
        // ── Group header (non-clickable) ──
        const group = document.createElement('div');
        group.className = 'sub-group';

        const groupHeader = document.createElement('div');
        groupHeader.className = 'sub-group-header';
        groupHeader.dataset.subIndex = String(subIndex);
        const groupItemIndexes = window.currentFlattenedItems
            .map((flatItem, flatIndex) => flatItem.subIndex === subIndex ? flatIndex : -1)
            .filter(flatIndex => flatIndex >= 0);
        const subtopicCompleted = groupItemIndexes.length > 0 &&
            groupItemIndexes.every(flatIndex => {
                const flatItem = window.currentFlattenedItems[flatIndex];
                const hasSavedScore = flatItem.type !== 'assessment' || Number(flatItem.sub?.attemptsUsed || 0) > 0;
                return flatIndex < window.currentUnlockedIdx && hasSavedScore;
            });
        groupHeader.innerHTML = `
            <span class="sub-group-num">${subIndex + 1}</span>
            <span class="sub-group-title">${sub.title}</span>
            ${subtopicCompleted ? '<span class="subtopic-complete-check" title="Completed"><i data-lucide="check"></i></span>' : ''}
        `;
        group.appendChild(groupHeader);

        // ── Child items ──
        const childItems = [];
        if (sub.contentType === 'zoom_link') {
            const flatIdx = window.currentFlattenedItems.findIndex(f => f.subIndex === subIndex && f.type === 'zoom');
            childItems.push({ label: 'Live Zoom Session', icon: 'calendar', flatIdx });
        } else if (sub.contentType && sub.contentType !== 'subtopic') {
            const flatIdx = window.currentFlattenedItems.findIndex(f => f.subIndex === subIndex && f.type === 'assessment');
            childItems.push({ label: sub.title, icon: 'assessment', flatIdx });
        }
        if (sub.documentationPath) {
            const flatIdx = window.currentFlattenedItems.findIndex(
                f => f.subIndex === subIndex && f.type === 'doc'
            );
            childItems.push({ label: 'Reading', icon: '📄', flatIdx });
        }
        if (sub.videoUrl || sub.videoUploadUrl) {
            const flatIdx = window.currentFlattenedItems.findIndex(
                f => f.subIndex === subIndex && f.type === 'video'
            );
            childItems.push({ label: 'Video', icon: '▶', flatIdx });
        }
        if (childItems.length === 0) {
            const flatIdx = window.currentFlattenedItems.findIndex(
                f => f.subIndex === subIndex && f.type === 'none'
            );
            childItems.push({ label: 'No content', icon: '—', flatIdx });
        }

        childItems.forEach(({ label, icon, flatIdx }) => {
            const btn = document.createElement('button');
            btn.className = 'subtopic-nav-item sub-child-item';
            btn.dataset.flatIdx = flatIdx;
            
            const flatItem = window.currentFlattenedItems[flatIdx];
            const itemAccessible = isLearningItemAccessible(topic, flatItem, flatIdx);
            // Check prerequisite and sequential lock state.
            if (!itemAccessible) {
                btn.classList.add('locked');
                const lockReason = flatItem?.sub?.contentType === 'mock_exam' ? 'Complete all required content first' : 'Locked';
                btn.innerHTML = `<span class="sub-child-icon" style="opacity: 0.5; margin-right: 0.3rem;"><i data-lucide="lock" style="width: 14px; height: 14px;"></i></span><span class="sub-child-label">${label}</span><span class="sub-child-lock-reason">${lockReason}</span>`;
            } else {
                btn.innerHTML = `<span class="sub-child-label">${label}</span>`;
            }

            btn.addEventListener('click', () => {
                if (isLearningItemAccessible(topic, flatItem, flatIdx)) {
                    openFlattenedItem(flatIdx);
                } else if (flatItem?.sub?.contentType === 'mock_exam') {
                    showToast('Complete all PDFs, videos, Pre-tests, Post-tests, and Practice Tests before taking the Mock Exam.', 'info');
                }
            });
            group.appendChild(btn);
        });

        navList.appendChild(group);
    });

    const requestedSidebarItem = window.currentFlattenedItems[openIdx];
    const initialAccessibleIndex = requestedSidebarItem && isLearningItemAccessible(topic, requestedSidebarItem, openIdx)
        ? openIdx
        : window.currentFlattenedItems.findIndex((flatItem, flatIndex) => isLearningItemAccessible(topic, flatItem, flatIndex));
    if (initialAccessibleIndex >= 0) window.currentLearningItemIndex = initialAccessibleIndex;

    // Replace the single-topic navigation with the complete subject outline.
    renderCompleteSubjectSidebar(topic);

    // Open target item
    if (window.currentFlattenedItems.length > 0) {
        if (initialAccessibleIndex >= 0) openFlattenedItem(initialAccessibleIndex);
    }
}

function renderCompleteSubjectSidebar(activeTopic) {
    const navList = $('subtopics-nav-list');
    if (!navList) return;
    navList.innerHTML = '';
    const subjectTopics = topics.filter(topic => Number(topic.subjectId) === Number(activeTopic.subjectId));

    subjectTopics.forEach(topic => {
        const topicIndex = topics.indexOf(topic);
        const items = getTopicFlatLearningItems(topic);
        const progressIndex = Number(state.topicProgressMap[topic.id] || 0);
        const preTestCompleted = hasCompletedSubjectPreTest(topic.subjectId);
        const policyCompleted = hasCompletedSubjectPolicy(topic.subjectId);
        const isPolicy = topic.isPolicyTopic === true || /policy/i.test(String(topic.title || ''));
        const isPreTestTopic = (topic.subtopics || []).some(subtopic => subtopic.contentType === 'pre_test');
        const topicUnlocked = isPolicy || preTestCompleted || (isPreTestTopic && policyCompleted);
        const requiredItems = items.filter(item => item.type !== 'none');
        const topicCompleted = requiredItems.length > 0 && requiredItems.every((item, index) => {
            const actualIndex = items.indexOf(item);
            const hasScore = item.type !== 'assessment' || Number(item.sub.attemptsUsed || 0) > 0;
            return actualIndex < progressIndex && hasScore;
        });

        const section = document.createElement('section');
        section.className = `learning-outline-topic ${topic.id === activeTopic.id ? 'active' : ''} ${topicUnlocked ? 'unlocked' : 'locked'} ${topicCompleted ? 'completed' : ''}`;
        const header = document.createElement('button');
        header.type = 'button';
        header.className = 'learning-outline-topic-header';
        header.innerHTML = `<span class="learning-outline-topic-number">${topic.sort_order || topicIndex + 1}</span><span><strong>${escapeHtml(topic.title)}</strong><small>${topicCompleted ? 'Completed' : (topicUnlocked ? 'Available' : 'Locked')}</small></span><i data-lucide="${topicCompleted ? 'check-circle' : (topicUnlocked ? 'chevron-right' : 'lock')}"></i>`;
        header.disabled = !topicUnlocked;
        header.addEventListener('click', () => {
            const firstAccessible = items.findIndex((item, itemIndex) => outlineItemAccessible(topic, item, itemIndex));
            if (firstAccessible < 0) return;
            pauseActiveLessonVideo();
            state.currentTopicIndex = topicIndex;
            renderLesson(firstAccessible);
        });
        section.appendChild(header);

        const contents = document.createElement('div');
        contents.className = 'learning-outline-contents';
        items.forEach((item, itemIndex) => {
            const accessible = topicUnlocked && outlineItemAccessible(topic, item, itemIndex);
            const hasScore = item.type !== 'assessment' || Number(item.sub.attemptsUsed || 0) > 0;
            const completed = itemIndex < progressIndex && hasScore;
            const label = item.type === 'doc' ? 'Reading' : (item.type === 'video' ? 'Video' : (item.type === 'zoom' ? 'Live Zoom Session' : (item.sub.title || 'Assessment')));
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `learning-outline-item ${topic.id === activeTopic.id && itemIndex === Number(window.currentLearningItemIndex) ? 'active' : ''} ${completed ? 'completed' : ''} ${accessible ? '' : 'locked'}`;
            button.disabled = !accessible;
            button.innerHTML = `<i data-lucide="${completed ? 'check' : (accessible ? (item.type === 'video' ? 'play' : (item.type === 'doc' ? 'file-text' : 'clipboard-check')) : 'lock')}"></i><span><strong>${escapeHtml(item.sub.title || topic.title)}</strong><small>${escapeHtml(label)}</small></span>`;
            button.addEventListener('click', () => {
                pauseActiveLessonVideo();
                state.currentTopicIndex = topicIndex;
                renderLesson(itemIndex);
            });
            contents.appendChild(button);
        });
        section.appendChild(contents);
        navList.appendChild(section);
    });
    if (window.lucide) lucide.createIcons({root: navList});
}

function outlineItemAccessible(topic, item, itemIndex) {
    if (item.sub.contentType === 'mock_exam') return false;
    const progressIndex = Number(state.topicProgressMap[topic.id] || 0);
    const preTestCompleted = hasCompletedSubjectPreTest(topic.subjectId);
    const isPolicy = topic.isPolicyTopic === true || /policy/i.test(String(topic.title || ''));
    const isPreTestTopic = (topic.subtopics || []).some(subtopic => subtopic.contentType === 'pre_test');
    if (!preTestCompleted && !isPolicy && !(isPreTestTopic && hasCompletedSubjectPolicy(topic.subjectId))) return false;
    return itemIndex <= progressIndex;
}

function openFlattenedItem(index) {
    const item = window.currentFlattenedItems[index];
    if (!item) return;
    pauseActiveLessonVideo();
    window.currentLearningItemIndex = index;

    // Update active nav item — match by data-flat-idx
    document.querySelectorAll('.sub-child-item').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.flatIdx) === index);
    });

    // Update header
    const numEl = $('subtopic-header-num');
    const ttlEl = $('subtopic-header-title');
    if (numEl) numEl.textContent = `${item.type === 'video' ? 'Video' : (item.type === 'zoom' ? 'Live Zoom Session' : (item.type === 'assessment' ? 'Assessment' : 'Reading'))} · Part ${item.subIndex + 1}`;
    if (ttlEl) ttlEl.textContent = item.sub.title;

    const videoContainer = $('video-container');
    const docsContainer  = $('docs-container');
    const assessmentContainer = $('subtopic-assessment-container');
    const zoomContainer = $('zoom-session-container');
    if (assessmentContainer) assessmentContainer.style.display = item.type === 'assessment' ? 'flex' : 'none';
    if (zoomContainer) zoomContainer.style.display = item.type === 'zoom' ? 'flex' : 'none';

    if (item.type === 'assessment') {
        if (videoContainer) videoContainer.style.display = 'none';
        if (docsContainer) docsContainer.style.display = 'none';
        renderSubtopicAssessment(item.sub);
    } else if (item.type === 'zoom') {
        if (videoContainer) videoContainer.style.display = 'none';
        if (docsContainer) docsContainer.style.display = 'none';
        renderZoomSession(item.sub);
    } else if (item.type === 'video') {
        if (videoContainer) videoContainer.style.display = 'flex';
        if (docsContainer)  docsContainer.style.display  = 'none';
        loadVideoForSubtopic(item.sub);
    } else if (item.type === 'doc') {
        if (videoContainer) videoContainer.style.display = 'none';
        if (docsContainer)  docsContainer.style.display  = 'flex';
        loadDocsForSubtopic(item.sub);
    } else {
        if (videoContainer) videoContainer.style.display = 'none';
        if (docsContainer)  docsContainer.style.display  = 'none';
    }

    // Keep one sequential Continue control visible across every learning item.
    const completeBar = $('mark-complete-bar');
    const completeBtn = $('mark-complete-btn');
    if (completeBar && completeBtn) {
        const isLastItem = index === window.currentFlattenedItems.length - 1;
        const currentTopic = topics[state.currentTopicIndex];
        const currentIsPolicy = currentTopic?.isPolicyTopic === true || /policy/i.test(String(currentTopic?.title || ''));
        const preTestTopicIndex = currentIsPolicy
            ? topics.findIndex(candidate =>
                Number(candidate.subjectId || 0) === Number(currentTopic?.subjectId || 0) &&
                (candidate.subtopics || []).some(subtopic => subtopic.contentType === 'pre_test')
            )
            : -1;
        const nextTopicIndex = preTestTopicIndex >= 0 && preTestTopicIndex !== state.currentTopicIndex
            ? preTestTopicIndex
            : topics.findIndex((candidate, topicIndex) =>
                topicIndex > state.currentTopicIndex &&
                Number(candidate.subjectId || 0) === Number(currentTopic?.subjectId || 0)
            );
        const documentPath = String(item.sub?.documentationPath || '').toLowerCase();
        const requiresPdfCompletion = item.type === 'doc' && /\.pdf(?:$|[?#])/.test(documentPath);
        const wasPreviouslyCompleted = index < window.currentUnlockedIdx;
        const requiresVideoCompletion = item.type === 'video' && Boolean(item.sub?.videoUploadUrl);
        const requiresAssessmentCompletion = item.type === 'assessment' && Number(item.sub?.attemptsUsed || 0) === 0;
        const requiresCompletionSignal = requiresPdfCompletion || requiresVideoCompletion || requiresAssessmentCompletion;
        const readyTitle = isLastItem
            ? (nextTopicIndex >= 0 ? 'Continue to the next topic.' : 'Complete this topic.')
            : 'Continue to the next item.';

        completeBar.style.display = 'flex';
        const hasSavedAssessmentScore = item.type !== 'assessment' || Number(item.sub?.attemptsUsed || 0) > 0;
        completeBtn.disabled = (requiresCompletionSignal && !wasPreviouslyCompleted) || !hasSavedAssessmentScore;
        completeBtn.textContent = isLastItem && nextTopicIndex >= 0 ? 'Continue to Next Topic' : 'Continue';
        completeBtn.dataset.readyTitle = readyTitle;
        completeBtn.title = completeBtn.disabled
            ? (requiresPdfCompletion
                ? 'Scroll to the bottom of the PDF to continue.'
                : (requiresVideoCompletion ? 'Finish the video to continue.' : 'Take the assessment to continue.'))
            : readyTitle;
        completeBtn.onclick = async () => {
            if (completeBtn.disabled) return;
            pauseActiveLessonVideo();
            const nextIdx = index + 1;

            if (nextIdx > window.currentUnlockedIdx) {
                completeBtn.disabled = true;

                try {
                    const savedProgress = await apiRequest('/api/progress/unlock', 'POST', {
                        topic_id: currentTopic.id,
                        subtopic_id: item.sub.id,
                        item_type: item.type
                    });
                    window.currentUnlockedIdx = Number(savedProgress.max_unlocked_index || 0);
                    state.topicProgressMap[currentTopic.id] = window.currentUnlockedIdx;
                } catch (e) {
                    console.error('Failed to save progress to backend');
                    completeBtn.disabled = false;
                    showToast(e.message || 'Progress could not be saved. Please try again.', 'error');
                    return;
                }
            }

            if (!isLastItem) {
                renderLesson(nextIdx);
            } else if (nextTopicIndex >= 0) {
                openTopic(nextTopicIndex);
            } else {
                completeBtn.disabled = true;
                completeBtn.textContent = 'Topic Completed';
                completeBtn.title = 'You have completed this topic.';
            }
        };

        if (requiresAssessmentCompletion === false && item.type === 'assessment' && !wasPreviouslyCompleted) {
            markCurrentLearningItemComplete(index);
        }
    }

    if (window.lucide) lucide.createIcons();
}

function renderZoomSession(sub) {
    $('zoom-session-title').textContent = sub.title || 'Live Review Session';
    $('zoom-session-description').textContent = sub.zoomDescription || 'Join the scheduled live review session using the button below.';
    const formatDate = value => value ? new Date(value).toLocaleString(undefined, {year:'numeric',month:'long',day:'numeric',hour:'numeric',minute:'2-digit'}) : 'Schedule to be announced';
    $('zoom-session-schedule').textContent = sub.zoomEndsAt
        ? `${formatDate(sub.zoomStartsAt)} – ${formatDate(sub.zoomEndsAt)}`
        : formatDate(sub.zoomStartsAt);
    const join = $('zoom-session-join');
    join.href = sub.zoomUrl || '#';
    join.style.display = sub.zoomUrl ? 'inline-flex' : 'none';
}

async function markCurrentLearningItemComplete(index) {
    if (window.currentLearningItemIndex !== index) return;
    const completeBtn = $('mark-complete-btn');
    if (!completeBtn) return;
    completeBtn.disabled = false;
    completeBtn.title = completeBtn.dataset.readyTitle || 'Continue.';

    const completedIndex = index + 1;
    if (completedIndex > window.currentUnlockedIdx) {
        const topic = topics[state.currentTopicIndex];
        const activeItem = window.currentFlattenedItems[index];
        try {
            const savedProgress = await apiRequest('/api/progress/unlock', 'POST', {
                topic_id: topic.id,
                subtopic_id: activeItem.sub.id,
                item_type: activeItem.type
            });
            window.currentUnlockedIdx = Number(savedProgress.max_unlocked_index || 0);
            state.topicProgressMap[topic.id] = window.currentUnlockedIdx;
        } catch (error) {
            console.error('Failed to save completed learning content');
            completeBtn.disabled = true;
            showToast(error.message || 'Progress could not be saved. Please try again.', 'error');
            return;
        }
    }

    const activeItem = window.currentFlattenedItems[index];
    if (activeItem) {
        const groupIndexes = window.currentFlattenedItems
            .map((flatItem, flatIndex) => flatItem.subIndex === activeItem.subIndex ? flatIndex : -1)
            .filter(flatIndex => flatIndex >= 0);
        const groupCompleted = groupIndexes.every(flatIndex => {
            const flatItem = window.currentFlattenedItems[flatIndex];
            const hasSavedScore = flatItem.type !== 'assessment' || Number(flatItem.sub?.attemptsUsed || 0) > 0;
            return flatIndex < window.currentUnlockedIdx && hasSavedScore;
        });
        const groupHeader = document.querySelector(`.sub-group-header[data-sub-index="${activeItem.subIndex}"]`);
        if (groupCompleted && groupHeader && !groupHeader.querySelector('.subtopic-complete-check')) {
            groupHeader.insertAdjacentHTML('beforeend', '<span class="subtopic-complete-check" title="Completed"><i data-lucide="check"></i></span>');
            if (window.lucide) lucide.createIcons({root: groupHeader});
        }
    }
}

function renderSubtopicAssessment(sub) {
    const labels = {pre_test:'Pre-test',post_test:'Post-test',practice_test:'Practice Test',mock_exam:'Mock Exam'};
    const label = labels[sub.contentType] || 'Assessment';
    $('subtopic-assessment-label').textContent = label;
    $('subtopic-assessment-title').textContent = sub.title || label;
    const practiceTestInstructions = 'Read each question carefully and select the best answer. Review and update your answers before submitting the test. Your latest score will be recorded, and you may retake the test within the allowed attempt limit.';
    $('subtopic-assessment-instructions').textContent = sub.contentType === 'practice_test'
        ? practiceTestInstructions
        : (sub.instructions || 'Complete this assessment to continue.');
    const attempts = $('subtopic-assessment-attempts');
    const resultLine = $('subtopic-assessment-result');
    const limit = sub.maximumAttempts;
    const timingLabel = sub.timeLimitMinutes ? `${sub.timeLimitMinutes} minutes` : 'No time limit';
    attempts.textContent = `${limit ? `${sub.attemptsUsed || 0} of ${limit} attempts used` : 'Unlimited attempts'} · ${timingLabel}`;
    if (resultLine) {
        const hasResult = sub.latestScore !== null && sub.latestScore !== undefined && sub.latestTotal !== null && sub.latestTotal !== undefined;
        resultLine.style.display = hasResult ? 'block' : 'none';
        resultLine.innerHTML = hasResult
            ? `Latest result: <strong>${Number(sub.latestScore).toLocaleString(undefined,{maximumFractionDigits:2})}/${Number(sub.latestTotal).toLocaleString(undefined,{maximumFractionDigits:2})}</strong>`
            : '';
    }
    const button = $('start-subtopic-assessment');
    const exhausted = limit && (sub.attemptsUsed || 0) >= limit;
    button.textContent = `Start ${label}`;
    button.disabled = Boolean(exhausted || !sub.questionCount);
    if (!sub.questionCount) attempts.textContent = 'No approved questions are available yet.';
    else if (exhausted) attempts.textContent = 'Maximum attempts reached.';
    button.onclick = async () => {
        const result = await apiRequest(`/api/courses/${currentCourseId}/subtopics/${sub.id}/assessment/questions`);
        if (!result?.success) return;
        activeSubtopicAssessmentId = sub.id;
        activeSubtopicAssessmentContext = 'topic';
        state.examType = 'subtopic_assessment';
        startQuiz(result.questions, {timeLimitMinutes: result.timeLimitMinutes});
    };
    const summaryButton = $('view-subtopic-assessment-summary');
    if (summaryButton) {
        summaryButton.style.display = exhausted && Number(sub.attemptsUsed || 0) > 0 ? '' : 'none';
        summaryButton.onclick = async () => {
            const result = await apiRequest(`/api/courses/${currentCourseId}/subtopics/${sub.id}/assessment/summary`);
            if (result?.success) showAssessmentSummary(result, () => { renderLesson(window.currentLearningItemIndex || 0); showScreen('lesson-screen'); });
        };
    }
}

const lessonVideoSessionPositions = new Map();
let activeLessonVideoKey = null;

function getLessonVideoKey(sub) {
    return `${currentCourseId || 'course'}:${sub.id || sub.title}:${sub.videoUploadUrl || sub.videoUrl || ''}`;
}

function pauseActiveLessonVideo() {
    const uploadedPlayer = $('uploaded-video-player');
    const embeddedPlayer = $('video-player');
    if (uploadedPlayer && uploadedPlayer.getAttribute('src')) {
        if (activeLessonVideoKey && Number.isFinite(uploadedPlayer.currentTime)) {
            lessonVideoSessionPositions.set(activeLessonVideoKey, uploadedPlayer.currentTime);
        }
        uploadedPlayer.pause();
    }
    if (embeddedPlayer && embeddedPlayer.getAttribute('src')) {
        const source = embeddedPlayer.getAttribute('src') || '';
        if (source.includes('youtube.com/embed/')) {
            embeddedPlayer.contentWindow?.postMessage(JSON.stringify({event:'command',func:'pauseVideo',args:[]}), '*');
        } else {
            // Cross-origin players such as Google Drive do not expose a pause API.
            // Unloading the preview guarantees that its audio/video stops.
            embeddedPlayer.removeAttribute('src');
        }
    }
}

function loadVideoForSubtopic(sub) {
    const player       = $('video-player');
    const uploadedPlayer = $('uploaded-video-player');
    const videoIframeWrap = $('video-iframe-wrap');
    const unavailable  = $('video-unavailable');
    const titleLabel   = $('video-title-label');
    const loadingOverlay = $('video-loading-overlay');
    const loadingLabel = $('video-loading-label');
    const loadingBar = $('video-loading-bar');
    const loadingPercentage = $('video-loading-percentage');

    const showVideoLoading = (label = 'Preparing secure video…') => {
        if (loadingLabel) loadingLabel.textContent = label;
        if (loadingOverlay) loadingOverlay.style.display = 'flex';
    };
    const hideVideoLoading = () => {
        if (loadingOverlay) loadingOverlay.style.display = 'none';
    };
    const updateVideoLoadingProgress = () => {
        if (!uploadedPlayer || !Number.isFinite(uploadedPlayer.duration) || uploadedPlayer.duration <= 0 || uploadedPlayer.buffered.length === 0) return;
        let bufferedEnd = 0;
        for (let index = 0; index < uploadedPlayer.buffered.length; index++) {
            bufferedEnd = Math.max(bufferedEnd, uploadedPlayer.buffered.end(index));
        }
        const percentage = Math.max(0, Math.min(100, Math.round((bufferedEnd / uploadedPlayer.duration) * 100)));
        if (loadingBar) loadingBar.style.width = `${percentage}%`;
        if (loadingPercentage) loadingPercentage.textContent = `${percentage}%`;
    };

    // Reset
    if (videoIframeWrap) videoIframeWrap.style.display = 'none';
    if (player) player.style.display = 'none';
    if (uploadedPlayer) { uploadedPlayer.pause(); uploadedPlayer.onended = null; uploadedPlayer.ontimeupdate = null; uploadedPlayer.onloadedmetadata = null; uploadedPlayer.onloadstart = null; uploadedPlayer.onprogress = null; uploadedPlayer.onwaiting = null; uploadedPlayer.oncanplay = null; uploadedPlayer.onplaying = null; uploadedPlayer.onerror = null; uploadedPlayer.removeAttribute('src'); uploadedPlayer.style.display = 'none'; }
    if (unavailable)     unavailable.style.display     = 'none';
    hideVideoLoading();
    if (loadingBar) loadingBar.style.width = '0%';
    if (loadingPercentage) loadingPercentage.textContent = '0%';

    if (titleLabel) titleLabel.textContent = sub.title || 'Video';

    if (!sub.videoUrl && !sub.videoUploadUrl) {
        if (unavailable) unavailable.style.display = 'flex';
        return;
    }

    // Parse YouTube URL to embed format
    const getEmbedUrl = (url) => {
        if (!url) return '';
        if (url.includes('drive.google.com')) {
            const fileMatch = url.match(/\/file\/d\/([^/]+)/);
            const idMatch = url.match(/[?&]id=([^&]+)/);
            const driveId = fileMatch?.[1] || idMatch?.[1];
            return driveId ? `https://drive.google.com/file/d/${driveId}/preview` : url;
        }
        let vidId = '';
        if (url.includes('youtube.com/watch?v=')) {
            vidId = url.split('v=')[1].split('&')[0];
        } else if (url.includes('youtu.be/')) {
            vidId = url.split('youtu.be/')[1].split('?')[0];
        } else if (url.includes('youtube.com/embed/')) {
            const separator = url.includes('?') ? '&' : '?';
            return `${url}${separator}enablejsapi=1`;
        }
        return vidId ? `https://www.youtube.com/embed/${vidId}?rel=0&enablejsapi=1` : url;
    };

    if (sub.videoUploadUrl && uploadedPlayer) {
        activeLessonVideoKey = getLessonVideoKey(sub);
        uploadedPlayer.disablePictureInPicture = true;
        uploadedPlayer.disableRemotePlayback = true;
        uploadedPlayer.src = sub.videoUploadUrl;
        uploadedPlayer.style.display = 'block';
        showVideoLoading();
        const learningItemIndex = window.currentLearningItemIndex;
        uploadedPlayer.onloadstart = () => showVideoLoading('Preparing secure video…');
        uploadedPlayer.onprogress = updateVideoLoadingProgress;
        uploadedPlayer.onwaiting = () => {
            updateVideoLoadingProgress();
            showVideoLoading('Buffering video…');
        };
        uploadedPlayer.oncanplay = hideVideoLoading;
        uploadedPlayer.onplaying = hideVideoLoading;
        uploadedPlayer.onerror = () => {
            showVideoLoading('Unable to load video. Please refresh and try again.');
            if (loadingPercentage) loadingPercentage.textContent = '';
        };
        uploadedPlayer.onloadedmetadata = () => {
            updateVideoLoadingProgress();
            const savedPosition = lessonVideoSessionPositions.get(activeLessonVideoKey);
            if (Number.isFinite(savedPosition) && savedPosition > 0 && savedPosition < uploadedPlayer.duration) {
                uploadedPlayer.currentTime = savedPosition;
            }
        };
        uploadedPlayer.ontimeupdate = () => {
            if (activeLessonVideoKey && Number.isFinite(uploadedPlayer.currentTime)) {
                lessonVideoSessionPositions.set(activeLessonVideoKey, uploadedPlayer.currentTime);
            }
        };
        uploadedPlayer.onended = () => markCurrentLearningItemComplete(learningItemIndex);
        if (titleLabel) titleLabel.textContent = sub.videoFilename || sub.title || 'Video';
    } else if (player) {
        hideVideoLoading();
        activeLessonVideoKey = getLessonVideoKey(sub);
        const embedUrl = getEmbedUrl(sub.videoUrl);
        if (player.getAttribute('src') !== embedUrl) player.src = embedUrl;
        player.style.display = 'block';
    }
    if (videoIframeWrap) videoIframeWrap.style.display = 'block';
}

function loadDocsForSubtopic(sub) {
    // Cancel any PDF rendering and completion listener left by the previous item.
    pdfRenderSequence++;
    const docsBtn       = $('docs-download-btn');
    const fullscreenBtn = $('docs-fullscreen-btn');
    const docsIframe    = $('docs-iframe');
    const docsIframeWrap = $('docs-iframe-wrap');
    const docsImg       = $('docs-img');
    const docsImgWrap   = $('docs-img-wrap');
    const docsFallback  = $('docs-fallback');
    const filenameLabel = $('docs-filename-label');
    const pdfPages = $('pdf-pages-container');

    // Reset
    if (docsIframeWrap) {
        docsIframeWrap.style.display = 'none';
        docsIframeWrap.onscroll = null;
        docsIframeWrap.scrollTop = 0;
    }
    if (docsIframe) { docsIframe.style.display = 'none'; docsIframe.removeAttribute('src'); }
    if (pdfPages) { pdfPages.style.display = 'none'; pdfPages.innerHTML = ''; }
    if (docsImgWrap)    docsImgWrap.style.display    = 'none';
    if (docsFallback)   docsFallback.style.display   = 'none';

    if (!sub.documentationPath) {
        if (docsBtn) docsBtn.style.display = 'none';
        if (fullscreenBtn) fullscreenBtn.style.display = 'none';
        if (docsFallback) docsFallback.style.display = 'flex';
        if (filenameLabel) filenameLabel.textContent = 'No document';
        return;
    }

    if (docsBtn) {
        docsBtn.style.display = 'inline-flex';
        docsBtn.href = sub.documentationPath;
    }
    if (fullscreenBtn) fullscreenBtn.style.display = 'flex';
    if (filenameLabel) filenameLabel.textContent = sub.documentationFilename || 'Document';

    const path  = sub.documentationPath.toLowerCase();
    const documentName = String(sub.documentationFilename || '').toLowerCase();
    const isPdf = sub.documentationType === 'pdf' || path.endsWith('.pdf') || documentName.endsWith('.pdf');
    const isImg = sub.documentationType === 'image' || /\.(jpeg|jpg|gif|png|webp)$/.test(path) || /\.(jpeg|jpg|gif|png|webp)$/.test(documentName);

    if (isImg) {
        if (docsImg) docsImg.src = sub.documentationPath;
        if (docsImgWrap) docsImgWrap.style.display = 'flex';
    } else if (isPdf) {
        if (docsIframeWrap) { docsIframeWrap.style.display = 'block'; docsIframeWrap.style.overflowY = 'auto'; }
        const learningItemIndex = Number(window.currentLearningItemIndex || 0);
        const shouldTrackCompletion = learningItemIndex >= Number(window.currentUnlockedIdx || 0);
        renderTrackedPdf(sub.documentationPath, shouldTrackCompletion);
    } else {
        if (docsFallback) {
            docsFallback.style.display = 'flex';
            docsFallback.innerHTML = `
                <div style="width:64px;height:64px;border-radius:16px;background:rgba(255,255,255,0.05);border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--text-muted);">
                    <i data-lucide="file-archive" style="width:32px;height:32px;"></i>
                </div>
                <p style="color:var(--text);font-weight:600;">Preview not available</p>
                <p style="color:var(--text-muted);font-size:0.9rem;">Use the Download button to view this file.</p>
            `;
            if (window.lucide) lucide.createIcons({ root: docsFallback });
        }
    }
}

let pdfRenderSequence = 0;
let pdfJsLoader = null;
async function renderTrackedPdf(path, shouldTrackCompletion = true) {
    const sequence = ++pdfRenderSequence;
    const learningItemIndex = window.currentLearningItemIndex;
    const wrap = $('docs-iframe-wrap');
    const pages = $('pdf-pages-container');
    if (!wrap || !pages) return;
    pages.style.display = 'flex';
    pages.innerHTML = '<div class="pdf-render-status">Opening PDF…</div>';
    try {
        if (!pdfJsLoader) {
            // The legacy PDF.js browser build supports Safari as well as Chromium browsers.
            pdfJsLoader = import('/vendor/pdfjs/pdf.legacy.min.mjs').then(module => {
                module.GlobalWorkerOptions.workerSrc = '/vendor/pdfjs/pdf.worker.legacy.min.mjs';
                return module;
            });
        }
        const pdfjs = await pdfJsLoader;
        const pdf = await pdfjs.getDocument(path).promise;
        if (sequence !== pdfRenderSequence) return;
        pages.innerHTML = '';
        const targetWidth = Math.max(320, Math.min(1000, wrap.clientWidth - 32));
        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
            const page = await pdf.getPage(pageNumber);
            if (sequence !== pdfRenderSequence) return;
            const base = page.getViewport({scale: 1});
            const viewport = page.getViewport({scale: targetWidth / base.width});
            const canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width); canvas.height = Math.floor(viewport.height);
            canvas.setAttribute('aria-label', `PDF page ${pageNumber} of ${pdf.numPages}`);
            pages.appendChild(canvas);
            await page.render({canvasContext: canvas.getContext('2d'), viewport}).promise;
        }
        if (!shouldTrackCompletion) {
            wrap.onscroll = null;
            return;
        }

        let completed = false;
        wrap.onscroll = () => {
            if (completed || sequence !== pdfRenderSequence) return;
            const atBottom = wrap.scrollTop + wrap.clientHeight >= wrap.scrollHeight - 24;
            if (!atBottom) return;
            completed = true;
            const notice = document.createElement('div');
            notice.className = 'pdf-auto-complete-notice'; notice.textContent = 'Document completed'; pages.appendChild(notice);
            markCurrentLearningItemComplete(learningItemIndex);
            showToast('Document completed. You may now continue.', 'success');
        };
    } catch (error) {
        pages.innerHTML = '<div class="pdf-render-status">The PDF preview could not be loaded. Use Download to open the document.</div>';
    }
}

const docsFullscreenBtn = $('docs-fullscreen-btn');
if (docsFullscreenBtn) {
    docsFullscreenBtn.addEventListener('click', async () => {
        const viewer = $('docs-container');
        if (!viewer) return;
        try {
            const fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement;
            if (fullscreenElement) {
                const exitFullscreen = document.exitFullscreen || document.webkitExitFullscreen;
                if (exitFullscreen) await exitFullscreen.call(document);
            } else {
                const requestFullscreen = viewer.requestFullscreen || viewer.webkitRequestFullscreen;
                if (!requestFullscreen) throw new Error('Fullscreen API unavailable');
                await requestFullscreen.call(viewer);
            }
        } catch (error) {
            showToast('Full-screen viewing is not available in this browser.', 'info');
        }
    });
    const updateDocsFullscreenButton = () => {
        const fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement;
        const isFullscreen = fullscreenElement === $('docs-container');
        docsFullscreenBtn.innerHTML = `<i data-lucide="${isFullscreen ? 'minimize-2' : 'maximize-2'}" style="width:14px;height:14px"></i>${isFullscreen ? 'Exit Full Screen' : 'View Full Screen'}`;
        if (window.lucide) lucide.createIcons({root: docsFullscreenBtn});
    };
    document.addEventListener('fullscreenchange', updateDocsFullscreenButton);
    document.addEventListener('webkitfullscreenchange', updateDocsFullscreenButton);
}



const backBtn = $('lesson-back-btn');
if (backBtn) backBtn.addEventListener('click', () => {
    pauseActiveLessonVideo();
    if (currentSubjectId !== null) renderTopics();
    showScreen('dashboard-screen');
});


// ─── Quiz ─────────────────────────────────────────────────
let quizData = [], qIndex = 0, score = 0, selected = null, answersList = [], reviewQuestions = new Set();
let assessmentReviewActive = false;
let editingQuestionFromReview = false;
let quizTimerInterval = null;
let quizTimerSeconds = 1200;

function formatTime(sec) {
    const m = Math.floor(sec / 60).toString().padStart(2, '0');
    const s = (sec % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
}

function startQuiz(data, settings = {}) {
    if (!Array.isArray(data) || data.length === 0) {
        showToast('No approved questions are available for this assessment yet.', 'info');
        return;
    }
    quizData = data; qIndex = 0; score = 0; answersList = []; reviewQuestions = new Set();
    assessmentReviewActive = false; editingQuestionFromReview = false;
    if ($('quiz-question-panel')) $('quiz-question-panel').style.display = '';
    if ($('assessment-review-panel')) $('assessment-review-panel').style.display = 'none';
    const assessmentTitle = $('quiz-assessment-title');
    if (assessmentTitle) assessmentTitle.textContent = state.examType === 'subtopic_assessment'
        ? (topics[state.currentTopicIndex]?.subtopics?.find(item => item.id === activeSubtopicAssessmentId)?.title || 'Assessment')
        : (state.examType === 'final' ? 'Mock Exam' : (state.examType === 'mid' ? 'Practice Test' : 'Topic Quiz'));
    const totalQEl = $('total-q');
    if (totalQEl) totalQEl.textContent = data.length;
    
    clearInterval(quizTimerInterval);
    const timerEl = $('quiz-timer');
    const configuredMinutes = Number(settings.timeLimitMinutes || 0);
    if (configuredMinutes > 0) {
        quizTimerSeconds = Math.max(60, Math.round(configuredMinutes * 60));
        timerEl.innerHTML = `<i data-lucide="timer" style="width: 16px; height: 16px; margin-right: 6px; vertical-align: text-bottom;"></i> <span id="quiz-timer-text" style="font-weight: bold; font-family: monospace; font-size: 1.1rem;">${formatTime(quizTimerSeconds)}</span>`;
        timerEl.style.display = 'inline-block';
        timerEl.style.padding = '0.4rem 0.8rem';
        timerEl.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
        timerEl.style.color = 'var(--wrong, #ef4444)';
        timerEl.style.borderRadius = '8px';
        timerEl.style.border = '1px solid rgba(239, 68, 68, 0.2)';
        timerEl.classList.remove('hidden');
        if (typeof lucide !== 'undefined') lucide.createIcons({ root: timerEl });
        
        quizTimerInterval = setInterval(() => {
            quizTimerSeconds--;
            const timerText = $('quiz-timer-text');
            if (timerText) timerText.textContent = formatTime(quizTimerSeconds);
            if (quizTimerSeconds <= 0) {
                clearInterval(quizTimerInterval);
                showToast('Time is up! Submitting exam...', 'info');
                if (qIndex < quizData.length && selected !== null && selected !== undefined) answersList[qIndex] = selected;
                // Auto submit with empty answers for remaining
                while (answersList.length < quizData.length) {
                    answersList.push(null);
                }
                finishQuiz();
            }
        }, 1000);
    } else {
        if (timerEl) timerEl.classList.add('hidden');
    }
    
    renderQuestion();
    showScreen('quiz-screen');
}

function renderQuestion() {
    const q = quizData[qIndex];
    const isSata = q.responseType === 'sata';
    const isGrid = q.responseType === 'grid';
    const isCloze = q.responseType === 'cloze';
    const isHighlight = q.responseType === 'highlight';
    const isStructured = isGrid || isCloze;
    const currentQEl = $('current-q');
    if (currentQEl) currentQEl.textContent = qIndex + 1;
    const previousBtn = $('previous-q-btn');
    if (previousBtn) { previousBtn.style.display = 'inline-flex'; previousBtn.disabled = qIndex === 0; }
    const reviewCheckbox = $('mark-review-checkbox');
    if (reviewCheckbox) {
        reviewCheckbox.closest('label').style.display = 'flex';
        reviewCheckbox.checked = reviewQuestions.has(qIndex);
    }
    const progressPercent = Math.round(((qIndex + 1) / quizData.length) * 100);
    const progressPercentEl = $('quiz-progress-percent');
    if (progressPercentEl) progressPercentEl.textContent = progressPercent;
    const responseLabel = $('quiz-response-label');
    if (responseLabel) responseLabel.textContent = isGrid ? 'Grid / Matrix' : (isCloze ? 'Cloze / Dropdown' : (isHighlight ? 'Highlighting' : (isSata ? 'Select All That Apply' : 'Multiple Choice')));
    const answerInstruction = $('quiz-answer-instruction');
    if (answerInstruction) answerInstruction.textContent = isGrid ? 'Complete every response cell in the matrix.' : (isCloze ? 'Select the best answer for every dropdown.' : (isHighlight ? 'Select every word or phrase that should be highlighted.' : (isSata ? 'Select all answers that apply.' : 'Select the best answer.')));
    const qTextEl = $('question-text');
    if (qTextEl) {
        qTextEl.textContent = isCloze ? '' : q.question + (isSata ? ' (Select all that apply)' : '');
        qTextEl.style.display = isCloze ? 'none' : '';
        qTextEl.style.fontSize = '1.25rem';
        qTextEl.style.fontWeight = '600';
        qTextEl.style.marginBottom = '1.5rem';
        qTextEl.style.color = 'var(--text)';
    }
    const questionImageContainer = $('question-image-container');
    const questionImage = $('question-image');
    if (questionImageContainer && questionImage) {
        if (q.imageUrl) {
            questionImage.src = q.imageUrl;
            questionImageContainer.style.display = 'block';
        } else {
            questionImage.removeAttribute('src');
            questionImageContainer.style.display = 'none';
        }
    }
    const progressBar = $('quiz-progress-bar');
    if (progressBar) progressBar.style.width = ((qIndex + 1) / quizData.length * 100) + '%';
    const rationaleBox = $('question-rationale');
    if (rationaleBox) { rationaleBox.style.display = 'none'; rationaleBox.innerHTML = ''; }

    const opts = $('options-container');
    if (opts) {
        opts.innerHTML = '';
        const savedAnswer = answersList[qIndex];
        selected = savedAnswer !== undefined
            ? (Array.isArray(savedAnswer) ? [...savedAnswer] : (isStructured && savedAnswer ? {...savedAnswer} : savedAnswer))
            : (isStructured ? {} : ((isSata || isHighlight) ? [] : null));
        const nextBtn = $('next-q-btn');
        if (nextBtn) {
            nextBtn.textContent = editingQuestionFromReview ? 'Save & Return to Review' : (qIndex === quizData.length - 1 ? 'Review Assessment' : 'Save & Continue');
            nextBtn.disabled = false;
            nextBtn.style.opacity = '1';
            nextBtn.style.cursor = 'pointer';
        }

        if (isGrid && q.responseConfig) {
            const config = q.responseConfig;
            const escapeText = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
            opts.innerHTML = `<div class="table-wrap"><table class="matrix-examinee-table"><thead><tr>${config.columns.map(column => `<th>${escapeText(column.label)}</th>`).join('')}</tr></thead><tbody>${config.rows.map(row => `<tr>${row.cells.map(cell => cell.type === 'static_text' ? `<td>${escapeText(cell.value)}</td>` : cell.type === 'sata' ? `<td class="matrix-sata-cell" data-key="${escapeText(row.key + '.' + cell.column_key)}">${cell.options.map(option => `<label><input type="checkbox" value="${escapeText(option.value)}"> <span>${escapeText(option.label)}</span></label>`).join('')}</td>` : `<td><select class="matrix-answer-select" data-key="${escapeText(row.key + '.' + cell.column_key)}"><option value="">Select an answer</option>${cell.options.map(option => `<option value="${escapeText(option.value)}">${escapeText(option.label)}</option>`).join('')}</select></td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
            const selects = [...opts.querySelectorAll('.matrix-answer-select')];
            const sataCells = [...opts.querySelectorAll('.matrix-sata-cell')];
            selects.forEach(select => { select.value = selected[select.dataset.key] || ''; });
            sataCells.forEach(cell => cell.querySelectorAll('input').forEach(input => {
                input.checked = Array.isArray(selected[cell.dataset.key]) && selected[cell.dataset.key].includes(input.value);
            }));
            const updateMatrixCompletion = () => {
                const complete = selects.every(item => item.value !== '') && sataCells.every(cell => cell.querySelector('input:checked'));
                if (nextBtn) { nextBtn.disabled = false; nextBtn.style.opacity = '1'; nextBtn.style.cursor = 'pointer'; }
            };
            selects.forEach(select => select.addEventListener('change', () => {
                if (select.value) selected[select.dataset.key] = select.value; else delete selected[select.dataset.key];
                updateMatrixCompletion();
            }));
            sataCells.forEach(cell => cell.querySelectorAll('input').forEach(input => input.addEventListener('change', () => {
                selected[cell.dataset.key] = [...cell.querySelectorAll('input:checked')].map(item => item.value);
                updateMatrixCompletion();
            })));
            return;
        }

        if (isCloze && q.responseConfig) {
            const config=q.responseConfig;
            const escapeText=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
            const blanks=new Map((config.blanks||[]).map(blank=>[blank.key,blank]));let cursor=0,html='<p class="cloze-question-text">',match;const regex=/{{\s*([a-zA-Z][a-zA-Z0-9_]*)\s*}}/g;const template=config.template||q.question||'';
            while((match=regex.exec(template))){html+=escapeText(template.slice(cursor,match.index));const blank=blanks.get(match[1]);html+=blank?`<select class="cloze-answer-select" data-key="${escapeText(blank.key)}"><option value="">Select an answer</option>${blank.options.map(option=>`<option value="${escapeText(option.value)}">${escapeText(option.label)}</option>`).join('')}</select>`:escapeText(match[0]);cursor=regex.lastIndex}html+=escapeText(template.slice(cursor))+'</p>';opts.innerHTML=html;
            const selects=[...opts.querySelectorAll('.cloze-answer-select')];
            selects.forEach(select=>{select.value=selected[select.dataset.key]||'';select.addEventListener('change',()=>{if(select.value)selected[select.dataset.key]=select.value;else delete selected[select.dataset.key];if(nextBtn)nextBtn.disabled=!selects.every(item=>item.value)})});
            if(nextBtn)nextBtn.disabled=!selects.every(item=>item.value);
            return;
        }

        if (isHighlight && q.responseConfig) {
            const escapeText = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
            opts.innerHTML = `<div class="highlight-question-passage">${(q.responseConfig.segments || []).map(segment => `<button type="button" class="highlight-choice" data-key="${escapeText(segment.key)}">${escapeText(segment.text)}</button>`).join(' ')}</div>`;
            opts.querySelectorAll('.highlight-choice').forEach(choice => {
                if (selected.includes(choice.dataset.key)) choice.classList.add('selected');
                choice.addEventListener('click', () => {
                    const key = choice.dataset.key;
                    const position = selected.indexOf(key);
                    if (position >= 0) { selected.splice(position, 1); choice.classList.remove('selected'); }
                    else { selected.push(key); choice.classList.add('selected'); }
                    if (nextBtn) nextBtn.disabled = selected.length === 0;
                });
            });
            if (nextBtn) nextBtn.disabled = selected.length === 0;
            return;
        }

        (q.options || []).forEach((opt, i) => {
            const btn = document.createElement('button');
            btn.className = 'quiz-option';
            
            const letter = String.fromCharCode(65 + i);
            btn.innerHTML = `
                <span class="opt-letter">${letter}</span>
                <span class="opt-text">${opt}</span>
            `;
            if (isSata ? selected.includes(i) : selected === i) btn.classList.add('selected');
            
            btn.addEventListener('click', () => {
                if (isSata) {
                    const position = selected.indexOf(i);
                    if (position >= 0) {
                        selected.splice(position, 1);
                        btn.classList.remove('selected');
                    } else {
                        selected.push(i);
                        btn.classList.add('selected');
                    }
                } else {
                    document.querySelectorAll('.quiz-option').forEach(b => b.classList.remove('selected'));
                    btn.classList.add('selected');
                    selected = i;
                }
                if (nextBtn) {
                    nextBtn.disabled = false;
                    nextBtn.style.opacity = '1';
                    nextBtn.style.cursor = 'pointer';
                }
            });
            opts.appendChild(btn);
        });
    }
}

const nextQBtn = $('next-q-btn');
const previousQBtn = $('previous-q-btn');
const markReviewCheckbox = $('mark-review-checkbox');
if (markReviewCheckbox) {
    markReviewCheckbox.addEventListener('change', () => {
        if (markReviewCheckbox.checked) reviewQuestions.add(qIndex);
        else reviewQuestions.delete(qIndex);
    });
}
if (previousQBtn) {
    previousQBtn.addEventListener('click', () => {
        if (qIndex === 0) return;
        const currentQuestion = quizData[qIndex];
        answersList[qIndex] = Array.isArray(selected)
            ? [...selected].sort((a, b) => a - b)
            : (['grid','cloze'].includes(currentQuestion.responseType) ? {...selected} : selected);
        qIndex--;
        renderQuestion();
        const examBody = document.querySelector('.exam-environment-body');
        if (examBody) examBody.scrollTop = 0;
    });
}
function revealQuestionRationale(question) {
    const box = $('question-rationale');
    if (!box || !question?.rationale || state.examType !== 'quiz') return false;
    box.innerHTML = `<strong>Rationale</strong><p>${String(question.rationale).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]))}</p>`;
    box.style.display = 'block';
    return true;
}

function assessmentAnswerIsComplete(answer) {
    if (answer === undefined || answer === null || answer === '') return false;
    if (Array.isArray(answer)) return answer.length > 0;
    if (typeof answer === 'object') {
        const values = Object.values(answer);
        return values.length > 0 && values.some(value => Array.isArray(value) ? value.length > 0 : value !== null && value !== '');
    }
    return true;
}

function showAssessmentReview() {
    assessmentReviewActive = true;
    editingQuestionFromReview = false;
    $('quiz-question-panel').style.display = 'none';
    $('assessment-review-panel').style.display = 'block';
    $('previous-q-btn').style.display = 'none';
    $('mark-review-checkbox').closest('label').style.display = 'none';
    $('next-q-btn').textContent = 'Submit Assessment';
    $('next-q-btn').disabled = false;
    if ($('current-q')) $('current-q').textContent = quizData.length;
    if ($('quiz-progress-percent')) $('quiz-progress-percent').textContent = '100';
    if ($('quiz-progress-bar')) $('quiz-progress-bar').style.width = '100%';

    const list = $('assessment-review-list');
    list.innerHTML = '';
    quizData.forEach((question, index) => {
        const answered = assessmentAnswerIsComplete(answersList[index]);
        const marked = reviewQuestions.has(index);
        const statusClass = marked ? 'review-orange' : (answered ? 'review-blue' : 'review-red');
        const statusText = marked ? 'Marked for review' : (answered ? 'Answered' : 'No answer');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `assessment-review-item ${statusClass}`;
        button.innerHTML = `<span class="assessment-review-number">${index + 1}</span><span class="assessment-review-copy"><strong>${statusText}</strong><span>${String(question.question || 'Question').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]))}</span></span>`;
        button.addEventListener('click', () => {
            assessmentReviewActive = false;
            editingQuestionFromReview = true;
            qIndex = index;
            $('assessment-review-panel').style.display = 'none';
            $('quiz-question-panel').style.display = '';
            renderQuestion();
            document.querySelector('.exam-environment-body').scrollTop = 0;
        });
        list.appendChild(button);
    });
    document.querySelector('.exam-environment-body').scrollTop = 0;
}

const quizCloseBtn = $('quiz-close-btn');
if (quizCloseBtn) {
    quizCloseBtn.addEventListener('click', () => {
        if (!window.confirm('Exit this assessment? Your current unanswered progress will not be submitted.')) return;
        clearInterval(quizTimerInterval);
        answersList = [];
        showScreen('dashboard-screen');
        renderDashboard();
    });
}
if (nextQBtn) {
    nextQBtn.addEventListener('click', () => {
        if (assessmentReviewActive) {
            finishQuiz();
            return;
        }

        const q = quizData[qIndex];
        const submittedAnswer = Array.isArray(selected) ? [...selected].sort((a, b) => a - b) : (['grid','cloze'].includes(q.responseType) ? {...selected} : selected);
        answersList[qIndex] = submittedAnswer;
        if (editingQuestionFromReview) {
            showAssessmentReview();
            return;
        }
        if (qIndex === quizData.length - 1) {
            showAssessmentReview();
            return;
        }
        qIndex++;
        renderQuestion();
        const reviewBody = document.querySelector('.exam-environment-body');
        if (reviewBody) reviewBody.scrollTop = 0;
        return;

        if (q.responseType === 'grid') {
            nextQBtn.disabled = true;
            document.querySelectorAll('.matrix-answer-select, .matrix-sata-cell input').forEach(control => control.disabled = true);
            const delay = revealQuestionRationale(q) ? 3000 : 400;
            setTimeout(() => { if (qIndex < quizData.length - 1) { qIndex++; renderQuestion(); } else finishQuiz(); }, delay);
            return;
        }
        const expectedAnswers = (q.correctAnswers || []).map(Number).sort((a, b) => a - b);
        const submittedAnswers = (Array.isArray(submittedAnswer) ? submittedAnswer : [submittedAnswer]).map(Number).sort((a, b) => a - b);
        const isCorrect = expectedAnswers.length === submittedAnswers.length && expectedAnswers.every((value, index) => value === submittedAnswers[index]);

        document.querySelectorAll('.quiz-option').forEach((btn, i) => {
            btn.disabled = true;
            btn.style.cursor = 'default';
            const letterEl = btn.querySelector('.opt-letter');
            
            // For regular quiz, we know answers index locally.
            // For final exam or mid exam, options grading is performed securely at submit!
            if (state.examType === 'quiz') {
                if (expectedAnswers.includes(i)) {
                    btn.classList.add('correct');
                    btn.style.borderColor = 'var(--success, #10b981)';
                    btn.style.backgroundColor = 'rgba(16, 185, 129, 0.05)';
                    if (letterEl) {
                        letterEl.style.background = 'var(--success, #10b981)';
                        letterEl.style.color = '#fff';
                        letterEl.style.borderColor = 'var(--success, #10b981)';
                        letterEl.innerHTML = `<i data-lucide="check" style="width: 16px; height: 16px;"></i>`;
                        if (window.lucide) lucide.createIcons({ root: letterEl });
                    }
                }
                else if (submittedAnswers.includes(i)) {
                    btn.classList.add('wrong');
                    btn.style.borderColor = 'var(--wrong, #ef4444)';
                    btn.style.backgroundColor = 'rgba(239, 68, 68, 0.05)';
                    if (letterEl) {
                        letterEl.style.background = 'var(--wrong, #ef4444)';
                        letterEl.style.color = '#fff';
                        letterEl.style.borderColor = 'var(--wrong, #ef4444)';
                        letterEl.innerHTML = `<i data-lucide="x" style="width: 16px; height: 16px;"></i>`;
                        if (window.lucide) lucide.createIcons({ root: letterEl });
                    }
                } else {
                    btn.style.opacity = '0.5';
                }
            } else {
                if (submittedAnswers.includes(i)) btn.classList.add('selected');
                else btn.style.opacity = '0.5';
            }
        });

        if (state.examType === 'quiz' && isCorrect) {
            score++;
        }

        const reviewDelay = revealQuestionRationale(q) ? 3000 : 1200;
        setTimeout(() => { 
            qIndex++; 
            qIndex < quizData.length ? renderQuestion() : finishQuiz(); 
        }, reviewDelay);
    });
}

let assessmentSummaryContinueAction = null;
let assessmentSummaryRationales = [];
let assessmentSummaryQuestions = [];
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
}
function showAssessmentSummary(data, continueAction) {
    assessmentSummaryContinueAction = continueAction;
    const activeAssessmentTitle = $('quiz-assessment-title')?.textContent?.trim() || 'Assessment';
    const summaryAssessmentTitle = $('assessment-summary-assessment-title');
    if (summaryAssessmentTitle) summaryAssessmentTitle.textContent = `${activeAssessmentTitle} Summary`;
    const score = Number(data.score || 0).toLocaleString(undefined, {maximumFractionDigits: 2});
    const total = Number(data.total || 0).toLocaleString(undefined, {maximumFractionDigits: 2});
    $('assessment-summary-title').textContent = data.passed ? 'Assessment Passed' : 'Assessment Completed';
    $('assessment-summary-score').textContent = `Score: ${score}/${total}`;
    const wrong = Array.isArray(data.incorrectQuestions) ? data.incorrectQuestions : [];
    assessmentSummaryQuestions = Array.isArray(data.questions) && data.questions.length
        ? data.questions
        : wrong.map(item => ({...item, correct:false}));
    assessmentSummaryRationales = assessmentSummaryQuestions.map(item => item.rationale || 'No rationale was provided.');
    const perfect = $('assessment-summary-perfect');
    perfect.style.display = assessmentSummaryQuestions.length && !assessmentSummaryQuestions.some(item => !item.correct) ? 'block' : 'none';
    perfect.textContent = data.reviewAvailable === false
        ? 'This earlier attempt has a saved score, but detailed answer review was not recorded.'
        : 'Excellent—there are no incorrect answers to review.';
    const list = $('assessment-summary-question-list');
    list.innerHTML = assessmentSummaryQuestions.map((item, index) => `
        <button type="button" class="assessment-summary-question-button ${item.correct ? 'is-correct' : 'is-wrong'}" data-summary-question="${index}" aria-label="Question ${index + 1}, ${item.correct ? 'correct' : 'incorrect'}">
            <strong>${index + 1}</strong><span>${item.correct ? 'Correct' : 'Incorrect'}</span>
        </button>`).join('');
    list.querySelectorAll('[data-summary-question]').forEach(button => button.addEventListener('click', () => {
        renderAssessmentSummaryQuestion(Number(button.dataset.summaryQuestion));
    }));
    if (assessmentSummaryQuestions.length) renderAssessmentSummaryQuestion(0);
    else $('assessment-summary-question-detail').innerHTML = '<p class="assessment-summary-unavailable">Detailed question review is not available for this earlier attempt.</p>';
    showScreen('assessment-summary-screen');
}

function renderAssessmentSummaryQuestion(index) {
    const item = assessmentSummaryQuestions[index];
    if (!item) return;
    document.querySelectorAll('[data-summary-question]').forEach(button =>
        button.classList.toggle('active', Number(button.dataset.summaryQuestion) === index)
    );
    const detail = $('assessment-summary-question-detail');
    detail.innerHTML = `
        <div class="assessment-summary-detail-heading"><span>Question ${index + 1} of ${assessmentSummaryQuestions.length}</span><strong class="${item.correct ? 'result-correct' : 'result-wrong'}">${item.correct ? 'Correct' : 'Incorrect'}</strong></div>
        ${item.imageUrl ? `<img class="assessment-summary-detail-image" src="${escapeHtml(item.imageUrl)}" alt="Question reference">` : ''}
        <h3>${escapeHtml(item.question || 'Question')}</h3>
        <div class="assessment-summary-answer-card ${item.correct ? 'answer-correct' : 'answer-wrong'}"><span>Your answer</span><p>${escapeHtml(item.learnerAnswer || 'No answer')}</p></div>
        <div class="assessment-summary-answer-card answer-correct"><span>Correct answer</span><p>${escapeHtml(item.correctAnswer || '')}</p></div>
        <div class="assessment-summary-rationale-card"><span>Rationale</span><p>${escapeHtml(item.rationale || 'No rationale was provided.')}</p></div>`;
    detail.scrollTop = 0;
}

const assessmentSummaryContinue = $('assessment-summary-continue');
if (assessmentSummaryContinue) assessmentSummaryContinue.addEventListener('click', () => {
    const action = assessmentSummaryContinueAction;
    assessmentSummaryContinueAction = null;
    if (action) action();
    else showScreen('dashboard-screen');
});

async function finishQuiz() {
    clearInterval(quizTimerInterval);
    if (state.examType === 'subtopic_assessment' && activeSubtopicAssessmentId) {
        const completedLearningItemIndex = window.currentLearningItemIndex;
        const assessmentContext = activeSubtopicAssessmentContext;
        try {
            const data = await apiRequest(`/api/courses/${currentCourseId}/subtopics/${activeSubtopicAssessmentId}/assessment/submit`, 'POST', {answers: answersList});
            const hasScoredResult = data?.success &&
                Number.isFinite(Number(data.score)) &&
                Number.isFinite(Number(data.total));
            if (hasScoredResult && assessmentContext !== 'subject_mock') {
                await markCurrentLearningItemComplete(completedLearningItemIndex);
            }
            if (hasScoredResult) showToast(`Assessment submitted. Score: ${data.score}/${data.total}`, data.passed ? 'success' : 'info');
            activeSubtopicAssessmentId = null;
            activeSubtopicAssessmentContext = 'topic';
            const refreshed = await apiRequest('/api/courses/' + currentCourseId + '/topics');
            if (refreshed?.success) {
                topics = refreshed.topics;
                subjects = refreshed.subjects || subjects;
                courseMockExamQuestionCount = Number(refreshed.mockExamQuestionCount || courseMockExamQuestionCount);
                courseMockExamLatestResult = refreshed.mockExamLatestResult || courseMockExamLatestResult;
                courseMockExamAttemptsUsed = Number(refreshed.mockExamAttemptsUsed || 0);
                courseMockExamMaximumAttempts = refreshed.mockExamMaximumAttempts === null ? null : Number(refreshed.mockExamMaximumAttempts);
                courseMockExamTimeLimitMinutes = refreshed.mockExamTimeLimitMinutes === null ? null : Number(refreshed.mockExamTimeLimitMinutes);
                courseMockExamPassed = Boolean(refreshed.mockExamPassed);
                courseMockExamCertificateAvailable = Boolean(refreshed.mockExamCertificateAvailable);
                if (currentSubjectId !== null) renderTopics();
            }
            showAssessmentSummary(data, () => {
                if (assessmentContext === 'subject_mock') {
                    renderSubjects();
                    showScreen('dashboard-screen');
                } else {
                    renderLesson(completedLearningItemIndex);
                    showScreen('lesson-screen');
                }
            });
        } catch (e) {}
        return;
    }
    
    if (state.examType === 'final' || state.examType === 'mid') {
        try {
            const data = await apiRequest('/api/courses/' + currentCourseId + '/exam/submit', 'POST', {
                'voucher_code': state.voucherCode || '',
                'answers': answersList
            });

            if (data && data.success) {
                if (data.passed) {
                    if (state.examType === 'mid') {
                        state.hasPassedMidterm = true;
                    } else {
                        state.hasCertificate = true;
                    }
                }
                courseMockExamLatestResult = {score:data.score,total:data.total,passed:data.passed};
                if (state.examType === 'final') {
                    courseMockExamAttemptsUsed += 1;
                    courseMockExamPassed = courseMockExamPassed || Boolean(data.passed);
                    courseMockExamCertificateAvailable = courseMockExamCertificateAvailable || Boolean(data.certificate);
                    if (data.certificate) {
                        if (!Array.isArray(state.certificates)) state.certificates = [];
                        if (!state.certificates.some(item => item.code === data.certificate.code)) {
                            state.certificates.push({...data.certificate, courseId: currentCourseId});
                        }
                        const completedCourse = courses.find(course => Number(course.id) === Number(currentCourseId));
                        if (completedCourse) {
                            completedCourse.has_certificate = true;
                            completedCourse.certificate = data.certificate;
                        }
                    }
                }
                showAssessmentSummary(data, () => {
                    renderDashboard();
                    if (state.examType === 'final' && data.passed && data.certificate) showCertificate(data.certificate);
                    else {
                        renderSubjects();
                        showScreen('dashboard-screen');
                    }
                });
            }
        } catch (e) {}
    } else {
        const topicId = topics[state.currentTopicIndex].id;
        try {
            const data = await apiRequest('/api/quiz/attempt', 'POST', {
                'topic_id': topicId,
                'answers': answersList
            });

            if (data && data.success) {
                state.completedTopics = data.completedTopics;
                showAssessmentSummary(data, () => {
                    renderDashboard();
                    showScreen('dashboard-screen');
                });
                return;
            }
        } catch (e) {}

        renderDashboard();
        showScreen('dashboard-screen');
    }
}


// ─── Certificate ──────────────────────────────────────────
function showCertificate(certInfo) {
    const dateEl = $('current-date');
    if (dateEl) {
        const d = new Date(certInfo.issuedAt);
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        dateEl.textContent = d.toLocaleDateString('en-US', options);
    }
    const credEl = $('cert-credential-id');
    const liCredEl = $('li-cred-id-modal');
    const liCredTopEl = $('li-cert-cred-id');
    
    if (credEl) credEl.textContent = certInfo.code;
    if (liCredEl) liCredEl.textContent = certInfo.code;
    if (liCredTopEl) liCredTopEl.textContent = certInfo.code;
    
    const userCertName = $('cert-user-name');
    if (userCertName) userCertName.textContent = certInfo.userName;

    const courseName = certInfo.courseName || 'Course';
    
    const courseNameLarge = $('cert-course-name-large');
    const liCourseTop = $('li-cert-course-name-top');
    const liModalCourse = $('li-modal-course-name');
    const certText = $('cert-course-name-text');
    const certBottom = $('cert-course-name-bottom');

    if (courseNameLarge) courseNameLarge.textContent = courseName;
    if (liCourseTop) liCourseTop.textContent = courseName;
    if (liModalCourse) liModalCourse.textContent = courseName;
    if (certText) certText.textContent = courseName;
    if (certBottom) certBottom.textContent = courseName;

    showScreen('certificate-screen');
}
// ─── Scroll Reveal Animations ─────────────────────────────
const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            revealObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.15 });

document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

// ─── Animated Counters ───────────────────────────────────
let countersStarted = false;
const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && !countersStarted) {
            countersStarted = true;
            document.querySelectorAll('.stat-number[data-count]').forEach(el => {
                const target = parseInt(el.dataset.count);
                let current = 0;
                const step = Math.max(1, Math.floor(target / 40));
                const timer = setInterval(() => {
                    current += step;
                    if (current >= target) { current = target; clearInterval(timer); }
                    el.textContent = current + (target > 1 ? '+' : '');
                }, 30);
            });
        }
    });
}, { threshold: 0.3 });

const statsSection = document.querySelector('.stats-section');
if (statsSection) counterObserver.observe(statsSection);

// ─── Theme Toggle ─────────────────────────────────────────
(function initTheme() {
    const saved = localStorage.getItem('cssm_theme');
    if (saved === 'dark') {
        applyTheme('dark');
    } else {
        applyTheme('light');
    }
})();

function applyTheme(mode) {
    const iconEls = [$('theme-icon'), $('landing-theme-icon')];
    if (mode === 'light') {
        document.body.classList.add('light-mode');
        localStorage.setItem('cssm_theme', 'light');
        iconEls.forEach(iconEl => {
            if (iconEl) iconEl.setAttribute('data-lucide', 'sun');
        });
        if (window.lucide) lucide.createIcons();
    } else {
        document.body.classList.remove('light-mode');
        localStorage.setItem('cssm_theme', 'dark');
        iconEls.forEach(iconEl => {
            if (iconEl) iconEl.setAttribute('data-lucide', 'moon');
        });
        if (window.lucide) lucide.createIcons();
    }
}

const themeToggleBtns = [$('theme-toggle-btn'), $('landing-theme-toggle')];
themeToggleBtns.forEach(btn => {
    if (btn) {
        btn.addEventListener('click', () => {
            const isLight = document.body.classList.contains('light-mode');
            applyTheme(isLight ? 'dark' : 'light');
        });
    }
});

// ─── Mobile Menu Toggle ──────────────────────────────────
const landingMenuBtn = $('landing-menu-btn');
const landingNavActions = $('landing-nav-actions');
if (landingMenuBtn && landingNavActions) {
    landingMenuBtn.addEventListener('click', () => {
        const isOpen = landingNavActions.classList.toggle('show');
        landingMenuBtn.classList.toggle('active', isOpen);
        landingMenuBtn.setAttribute('aria-expanded', String(isOpen));
    });
}

const dashboardMenuBtn = $('dashboard-menu-btn');
const dashboardNavActions = $('dashboard-nav-actions');
if (dashboardMenuBtn && dashboardNavActions) {
    dashboardMenuBtn.addEventListener('click', () => {
        dashboardNavActions.classList.toggle('show');
    });
}

// Check for successful Xendit return
function checkXenditReturn() {
    const params = new URLSearchParams(window.location.search);
    if (params.has('voucher_success')) {
        const code = params.get('voucher_success');
        const enrolledBatch = courses.find(course => course.is_enrolled);
        if (enrolledBatch) {
            const displayPrice = `${enrolledBatch.currency_symbol || '₱'}${Number(enrolledBatch.display_price ?? 0).toFixed(2)}${enrolledBatch.currency_code === 'USD' ? ' USD' : ''}`;
            const billingPrice = `${enrolledBatch.billing_currency_symbol || '₱'}${Number(enrolledBatch.billing_price ?? 0).toFixed(2)} ${enrolledBatch.billing_currency_code || 'PHP'}`;
            if ($('purchase-course-name')) $('purchase-course-name').textContent = `${enrolledBatch.batch_name} — ${enrolledBatch.title}`;
            if ($('purchase-course-price')) $('purchase-course-price').textContent = displayPrice;
            if ($('purchase-price-summary')) $('purchase-price-summary').textContent = `Price: ${displayPrice}`;
            if ($('purchase-billing-price')) $('purchase-billing-price').textContent = billingPrice;
        }
        
        // Show success modal
        const codeEl = $('generated-code');
        if (codeEl) codeEl.textContent = code;
        const s1 = $('buy-step-1');
        const s2 = $('buy-step-2');
        if (s1) s1.classList.add('hidden');
        if (s2) s2.classList.remove('hidden');
        
        state.hasBoughtVoucher = true;
        localStorage.setItem('cssm_bought_voucher', 'true');
        updateVoucherButtons();
        
        openModal('modal-buy-voucher');
        showToast('Payment successful!', 'success');
        fetchNotifications();
        
        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

// ─── Notification UI & API Logic ─────────────────────────────
async function fetchNotifications() {
    if (!state.user) return;
    try {
        const data = await apiRequest('/api/notifications');
        if (data && data.success) {
            renderNotifications(data.notifications);
        }
    } catch (e) {
        console.error("Failed to fetch notifications:", e);
    }
}

function renderNotifications(notifs) {
    const list = $('notif-list');
    const badge = $('notif-badge');
    if (!list) return;

    list.innerHTML = '';
    const unreadCount = notifs.filter(n => !n.is_read).length;

    if (badge) {
        if (unreadCount > 0) {
            badge.textContent = unreadCount;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    if (notifs.length === 0) {
        list.innerHTML = '<p class="notif-empty">No notifications yet</p>';
        return;
    }

    notifs.forEach(n => {
        const item = document.createElement('div');
        item.className = `notif-item ${n.is_read ? '' : 'unread'}`;
        
        const date = new Date(n.created_at);
        const timeStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        item.innerHTML = `
            <div class="notif-item-title">${n.title}</div>
            <div class="notif-item-message">${n.message}</div>
            <div class="notif-item-time">${timeStr}</div>
        `;

        if (!n.is_read) {
            item.addEventListener('click', async () => {
                try {
                    await apiRequest(`/api/notifications/${n.id}/read`, 'POST');
                    n.is_read = true;
                    item.classList.remove('unread');
                    const newUnread = notifs.filter(x => !x.is_read).length;
                    if (badge) {
                        if (newUnread > 0) {
                            badge.textContent = newUnread;
                        } else {
                            badge.classList.add('hidden');
                        }
                    }
                } catch (e) {}
            });
        }
        list.appendChild(item);
    });
}

// Attach Event Listeners for Notifications
const notifBtn = $('notif-btn');
const notifDropdown = $('notif-dropdown');
const notifClearAll = $('notif-clear-all');

if (notifBtn && notifDropdown) {
    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        notifDropdown.classList.toggle('hidden');
        if (!notifDropdown.classList.contains('hidden')) {
            fetchNotifications();
        }
    });

    document.addEventListener('click', (e) => {
        if (notifDropdown && !notifDropdown.contains(e.target) && e.target !== notifBtn && !notifBtn.contains(e.target)) {
            notifDropdown.classList.add('hidden');
        }
    });
}

if (notifClearAll) {
    notifClearAll.addEventListener('click', async (e) => {
        e.stopPropagation();
        try {
            await apiRequest('/api/notifications/read-all', 'POST');
            fetchNotifications();
        } catch (e) {}
    });
}

// ─── Certificate Actions ──────────────────────────────────
function copyCertId() {
    const credEl = document.getElementById('cert-credential-id');
    if (credEl && credEl.textContent) {
        navigator.clipboard.writeText(credEl.textContent).then(() => {
            showToast('Certificate ID copied to clipboard!', 'success');
        }).catch(err => {
            showToast('Failed to copy ID', 'error');
        });
    }
}

function downloadCertificate() {
    const certNode = document.getElementById('certificate');
    if (!certNode) {
        alert("Error: Certificate not found on page.");
        return;
    }
    
    try {
        const originalShadow = certNode.style.boxShadow;
        const originalTransform = certNode.style.transform;
        const originalAspectRatio = certNode.style.aspectRatio;
        
        const rect = certNode.getBoundingClientRect();
        const w = Math.round(rect.width) || 520;
        const h = Math.round(rect.height) || 402;
        
        certNode.style.width = w + 'px';
        certNode.style.height = h + 'px';
        certNode.style.aspectRatio = 'auto';
        certNode.style.boxShadow = 'none';
        certNode.style.transform = 'none';
        
        html2canvas(certNode, {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            width: w,
            height: h
        }).then(canvas => {
            certNode.style.boxShadow = originalShadow;
            certNode.style.transform = originalTransform;
            certNode.style.aspectRatio = originalAspectRatio;
            certNode.style.width = '';
            certNode.style.height = '';
            
            const userName = (state.user && state.user.name) ? state.user.name.replace(/[^a-zA-Z0-9]/g, '_') : 'Learner';
            const fileName = `Artemis_2_0_Certificate_${userName}.png`;
            
            const link = document.createElement('a');
            link.download = fileName;
            link.href = canvas.toDataURL('image/png', 1.0);
            document.body.appendChild(link);
            link.click();
            setTimeout(() => document.body.removeChild(link), 100);
            
        }).catch(err => {
            certNode.style.boxShadow = originalShadow;
            certNode.style.transform = originalTransform;
            certNode.style.aspectRatio = originalAspectRatio;
            certNode.style.width = '';
            certNode.style.height = '';
            alert("Error rendering image. Please try another browser. Details: " + err);
        });
    } catch (e) {
        alert("Fatal error setting up download: " + e);
    }
}

function shareOnLinkedIn() {
    const courseNameEl = document.getElementById('li-cert-course-name-top');
    const courseName = courseNameEl ? courseNameEl.textContent : 'a course';
    const text = encodeURIComponent(`I successfully completed ${courseName} and passed the Mock Exam at Artemis 2.0! View my new credential. #ArtemisLearning #ExamReady`);
    const linkedInUrl = `https://www.linkedin.com/feed/?shareActive=true&text=${text}`;
    
    window.open(linkedInUrl, '_blank', 'noopener,noreferrer');
}

// Global ESC handler to close modals
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (typeof closeModal === 'function') closeModal();
        if (typeof hideCertificate === 'function') hideCertificate();
        
        const videoOverlay = $('video-modal-overlay');
        if (videoOverlay && !videoOverlay.classList.contains('hidden')) {
            videoOverlay.classList.add('hidden');
            const vp = $('video-player');
            if (vp) vp.pause();
        }
        
        const docOverlay = $('doc-modal-overlay');
        if (docOverlay && !docOverlay.classList.contains('hidden')) {
            docOverlay.classList.add('hidden');
        }
    }
});

const ecBtn = $('explore-courses-btn');
if (ecBtn) {
    ecBtn.addEventListener('click', () => {
        const lastCourseId = localStorage.getItem('last_course_id');
        const cContainer = $('courses-container');
        if (cContainer && cContainer.children.length > 0) {
            let targetCard = null;
            if (lastCourseId) {
                const idx = courses.findIndex(c => c.id == lastCourseId);
                if (idx !== -1 && cContainer.children[idx]) {
                    targetCard = cContainer.children[idx];
                }
            }
            if (!targetCard) {
                targetCard = cContainer.children[0];
            }
            targetCard.click();
        }
    });
}

// Layout Toggles
function applyLayoutMode(mode, animate = true) {
    state.courseLayout = mode === 'grid' ? 'grid' : 'list';
    document.querySelectorAll('.topics-grid').forEach(grid => {
        if (animate) {
            grid.style.transition = 'opacity 0.15s ease';
            grid.style.opacity = '0';
        }
        const update = () => {
            grid.classList.toggle('grid-view', state.courseLayout === 'grid');
            grid.style.opacity = '1';
        };
        animate ? setTimeout(update, 150) : update();
    });

    document.querySelectorAll('.view-grid-btn').forEach(button => {
        button.classList.toggle('active-layout', state.courseLayout === 'grid');
        button.style.color = state.courseLayout === 'grid' ? 'var(--accent)' : '';
    });
    document.querySelectorAll('.view-list-btn').forEach(button => {
        button.classList.toggle('active-layout', state.courseLayout === 'list');
        button.style.color = state.courseLayout === 'list' ? 'var(--accent)' : '';
    });

    if (state.user) {
        const key = `artemis_course_layout_${String(state.user.email || 'learner').toLowerCase()}`;
        localStorage.setItem(key, state.courseLayout);
    }
}

document.querySelectorAll('.view-grid-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        applyLayoutMode('grid');
    });
});

document.querySelectorAll('.view-list-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        applyLayoutMode('list');
    });
});

// "No CSS" gimmick link
const noCssBtn = $('hero-no-css-btn');
if (noCssBtn) {
    noCssBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const styles = document.querySelectorAll('link[rel="stylesheet"], style');
        
        // Disable all CSS
        styles.forEach(el => el.disabled = true);
        
        // Create an "Exit" button with hardcoded inline styles so it looks like the landing page button
        const exitBtn = document.createElement('button');
        exitBtn.textContent = 'Bring CSS Back';
        exitBtn.style.cssText = `
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: linear-gradient(135deg, #7c3aed, #4f46e5);
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-family: 'Plus Jakarta Sans', sans-serif, system-ui;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            z-index: 99999;
        `;
        
        exitBtn.addEventListener('click', () => {
            styles.forEach(el => el.disabled = false);
            exitBtn.remove();
        });
        
        document.body.appendChild(exitBtn);
    });
}
