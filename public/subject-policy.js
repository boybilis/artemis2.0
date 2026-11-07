(() => {
    const dialog = document.getElementById('subject-policy-dialog');
    if (!dialog) return;
    const check = document.getElementById('subject-policy-agree-check');
    const accept = document.getElementById('subject-policy-accept');
    const decline = document.getElementById('subject-policy-decline');
    let context = null, saving = false;
    const sameContext = () => context && context.userId === state.user?.id
        && context.courseId === currentCourseId && context.batchId === currentBatchId
        && Number(context.subjectId) === Number(currentSubjectId);
    window.maybePromptSubjectPolicy = topic => {
        if (!topic || !needsSubjectPolicyAcceptance(topic.subjectId)
            || !isPolicyLearningTopic(topic) || !hasReadSubjectPolicy(topic.subjectId)) return false;
        if (dialog.open) return true;
        if (Number(currentSubjectId) !== Number(topic.subjectId) || !state.user) return false;
        context = {userId:state.user.id, courseId:currentCourseId, batchId:currentBatchId, subjectId:topic.subjectId};
        check.checked = false; accept.disabled = true; decline.disabled = false;
        dialog.showModal();
        return true;
    };
    check.addEventListener('change', () => { accept.disabled = saving || !check.checked; });
    function declinePolicy() {
        if (saving) return;
        const valid = sameContext();
        dialog.close(); context = null;
        if (!valid) return;
        pauseActiveLessonVideo(); currentSubjectId = null;
        renderSubjects(); showScreen('dashboard-screen');
    }
    decline.addEventListener('click', declinePolicy);
    dialog.addEventListener('cancel', event => { event.preventDefault(); declinePolicy(); });
    accept.addEventListener('click', async () => {
        if (saving || !check.checked || !sameContext()) return;
        saving = true; accept.disabled = true; decline.disabled = true; check.disabled = true;
        try {
            const result = await apiRequest(`/api/courses/${context.courseId}/subjects/${context.subjectId}/policy/accept`, 'POST',
                {agreed:true, batch_id:context.batchId}, {silent:true});
            if (!result.success || !result.policyAccepted) throw new Error('Agreement acceptance could not be saved. Please try again.');
            if (!sameContext()) { dialog.close(); context = null; return; }
            const subject = subjects.find(item => Number(item.id) === Number(context.subjectId));
            if (subject) subject.policyAccepted = true;
            if (result.policyTopicId && !state.completedTopics?.includes(result.policyTopicId)) {
                state.completedTopics = [...(state.completedTopics || []), result.policyTopicId];
            }
            const activeTopic = topics[state.currentTopicIndex];
            dialog.close(); context = null;
            if (activeTopic) renderCompleteSubjectSidebar(activeTopic);
            showToast(result.message, 'success');
        } catch (error) {
            if (sameContext()) showToast(error.message || 'Agreement acceptance could not be saved. Please try again.', 'error');
        } finally {
            saving = false; check.disabled = false; decline.disabled = false; accept.disabled = !check.checked;
            if (!sameContext()) { dialog.close(); context = null; }
        }
    });
    // Dismiss a stale prompt on logout or navigation; never apply it to another account/subject.
    new MutationObserver(() => {
        if (dialog.open && !saving && (!sameContext() || !document.getElementById('lesson-screen')?.classList.contains('active'))) {
            dialog.close(); context = null;
        }
    }).observe(document.getElementById('lesson-screen'), {attributes:true, attributeFilter:['class']});
})();
