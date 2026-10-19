let testBankRecovery = null;
function testBankDraftKey(bank,quiz) { return `artemis_tb_draft_${state.user?.id}_${bank}_${quiz}`; }
function readTestBankDraft(bank,quiz) {
    try { return JSON.parse(localStorage.getItem(testBankDraftKey(bank,quiz)) || 'null'); } catch { return null; }
}
function latestTestBankDraft(bank) {
    try {
        const prefix=`artemis_tb_draft_${state.user?.id}_${bank}_`;
        return Object.keys(localStorage).filter(key=>key.startsWith(prefix)).map(key=>JSON.parse(localStorage.getItem(key))).filter(Boolean).sort((a,b)=>b.savedAt-a.savedAt)[0] || null;
    } catch { return null; }
}
function beginTestBankRecovery(data,bank,quiz) {
    const local=readTestBankDraft(bank,quiz);
    const draft=local?.token===data.attemptToken ? local : data.draft || {};
    testBankRecovery={bank,quiz,token:data.attemptToken,title:data.title,type:data.testType,total:data.totalQuestions,minutes:data.timeLimitMinutes,
        deadline:data.deadline ? Date.now()+Math.max(0,data.deadline-data.serverTime)*1000 : null,pending:false};
    if(!document.getElementById('test-bank-save-status')){
        const status=document.createElement('small');status.id='test-bank-save-status';status.setAttribute('role','status');status.style.cssText='font-size:.7rem;max-width:220px';
        document.querySelector('#quiz-screen .exam-footer-controls')?.append(status);
    }
    document.getElementById('test-bank-save-status').textContent='Progress recovery enabled';
    return draft;
}
async function retrySavedTestBankSubmission(bank,quiz) {
    const draft=readTestBankDraft(bank,quiz);
    if(!draft?.submitting)return false;
    const result=await apiRequest(`/api/test-banks/${bank}/quizzes/${quiz}/submit`,'POST',{attempt_token:draft.token,answers:draft.answers});
    if(!result.success || !result.attemptId)throw new Error('Submission has not been confirmed. Please retry.');
    clearTestBankRecovery(bank,quiz);activeTestBankQuizId=null;
    const title=document.getElementById('quiz-assessment-title');title.dataset.examTitle=draft.title;title.dataset.testType=draft.type || '';state.examType='test_bank';
    await exitTestBankFullscreen().catch(()=>{});await showAssessmentResultModal(result);
    showAssessmentSummary(result,()=>{showScreen('dashboard-screen');openTestBankWorkspace(bank);});
    return true;
}
function persistTestBankDraft() {
    if(!testBankRecovery || !activeTestBankQuizId || state.examType!=='test_bank' || !document.getElementById('quiz-screen')?.classList.contains('active'))return null;
    const answers=answersList.slice();
    if(!assessmentReviewActive && quizData[qIndex] && selected!==undefined) answers[qIndex]=selected;
    const draft={...testBankRecovery,answers,marked:[...reviewQuestions],index:qIndex,savedAt:Date.now(),submitting:testBankRecovery.pending};
    try { localStorage.setItem(testBankDraftKey(draft.bank,draft.quiz),JSON.stringify(draft)); } catch {}
    return draft;
}
function clearTestBankRecovery(bank,quiz) {
    try { localStorage.removeItem(testBankDraftKey(bank,quiz)); } catch {}
    if(testBankRecovery?.bank===bank && testBankRecovery?.quiz===quiz)testBankRecovery=null;
}
let testBankDraftSaving = false;
async function syncTestBankDraft() {
    const draft=persistTestBankDraft();
    if(!draft || testBankDraftSaving || draft.submitting || !navigator.onLine)return;
    testBankDraftSaving=true;
    try {
        const response=await fetch(`/api/test-banks/${draft.bank}/quizzes/${draft.quiz}/draft`,{method:'POST',credentials:'same-origin',keepalive:true,
            headers:{Accept:'application/json','Content-Type':'application/json','X-XSRF-TOKEN':getCsrfToken()},
            body:JSON.stringify({attempt_token:draft.token,answers:draft.answers,marked:draft.marked,index:draft.index})});
        const status=document.getElementById('test-bank-save-status');
        if(status)status.textContent=response.ok?'Progress saved':'Saved on this browser · server sync pending';
    } catch { const status=document.getElementById('test-bank-save-status');if(status)status.textContent='Saved on this browser · offline'; }
    finally{testBankDraftSaving=false;}
}
setInterval(syncTestBankDraft,10000);
document.addEventListener('click',event=>{if(event.target.closest('#options-container'))queueMicrotask(()=>{persistTestBankDraft();syncTestBankDraft();});});
document.addEventListener('change',event=>{if(event.target.id==='mark-review-checkbox')syncTestBankDraft();});
window.addEventListener('beforeunload',()=>{persistTestBankDraft();syncTestBankDraft();});
window.addEventListener('offline',()=>{persistTestBankDraft();});
window.addEventListener('online',()=>{if(testBankRecovery?.pending && activeTestBankQuizId)finishQuiz();else syncTestBankDraft();});
