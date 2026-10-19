(() => {
    let signingOut=false;
    window.handleLearnerSessionReplaced=()=>{
        if(signingOut || !state.user)return;
        signingOut=true;
        // Preserve the draft; losing a login is not an explicit exam cancellation.
        persistTestBankDraft();
        clearInterval(quizTimerInterval);
        testBankExamActive=false;
        activeTestBankQuizId=null;
        testBankRecovery=null;
        document.querySelectorAll('.test-bank-exit-warning-backdrop').forEach(element=>element.remove());
        testBankExitWarningOpen=false;
        exitTestBankFullscreen().catch(()=>{});
        document.querySelectorAll('dialog[open]').forEach(dialog=>dialog.close());
        resetLearnerSessionState();state.user=null;
        showScreen('landing-screen');
        showToast('You have been signed out. This account may have been signed in on another browser or device. Your saved exam progress has not been cancelled.','info');
        signingOut=false;
    };
    let checking=false;
    async function checkSession(){
        if(checking || !state.user || state.user.role!=='student' || !navigator.onLine)return;
        checking=true;
        try {
            const response=await fetch('/api/auth/session',{credentials:'same-origin',headers:{Accept:'application/json'}});
            const result=await response.json();
            if(result.code==='session_replaced' || (response.ok && !result.user))window.handleLearnerSessionReplaced();
        }catch(_){}finally{checking=false;}
    }
    setInterval(checkSession,15000);
    window.addEventListener('focus',checkSession);
    window.addEventListener('online',checkSession);
})();
