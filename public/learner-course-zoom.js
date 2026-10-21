(() => {
    const pendingKeys=new Set();
    let lastAppliedKey=null;
    let lastPayload=null;
    function isCourseVisible() {
        if (!state.user || !currentCourseId || !currentBatchId || document.hidden || navigator.onLine===false) return false;
        return document.getElementById('lesson-screen')?.classList.contains('active') ||
            (document.getElementById('dashboard-screen')?.classList.contains('active') &&
            document.getElementById('course-details-area')?.style.display!=='none' &&
            !document.getElementById('test-bank-workspace-area')?.classList.contains('active') &&
            document.getElementById('subjects-list-area')?.style.display!=='none');
    }
    function applyWorkspaceZoom(incoming) {
        const inWorkspace=document.getElementById('lesson-screen')?.classList.contains('active');
        const activeTopic=inWorkspace ? topics[state.currentTopicIndex] : null;
        const activeItem=window.currentFlattenedItems?.[window.currentLearningItemIndex];
        const activeTopicId=activeTopic?.id;
        // Keep master lesson objects and their order. Replace only batch-only overlays.
        topics=topics.filter(topic=>!topic.isBatchZoomTopic);
        topics.forEach(topic=>{topic.subtopics=(topic.subtopics||[]).filter(sub=>!sub.isBatchZoom);});
        incoming.forEach(placement=>{
            const target=topics.find(topic=>Number(topic.id)===Number(placement.id));
            if(target)target.subtopics.push(...placement.subtopics);
            else if(placement.isBatchZoomTopic && subjects.some(subject=>Number(subject.id)===Number(placement.subjectId)))topics.push(placement);
        });
        if(activeTopicId!==undefined && state.currentTopicIndex!==null) {
            const newIndex=topics.findIndex(topic=>topic.id===activeTopicId);
            if(newIndex<0) {
                if(activeTopic?.isBatchZoomTopic)openSubjectLearningWorkspace(activeTopic.subjectId);
                return;
            }
            state.currentTopicIndex=newIndex;
            if(activeItem?.sub.isBatchZoom) {
                const itemIndex=getTopicFlatLearningItems(topics[newIndex]).findIndex(item=>item.sub.id===activeItem.sub.id);
                if(itemIndex>=0)renderLesson(itemIndex);
                else openSubjectLearningWorkspace(activeTopic.subjectId);
            } else {
                // Refresh the outline only: do not reload a video, document, or assessment.
                renderCompleteSubjectSidebar(topics[newIndex]);
            }
        } else if(document.getElementById('lesson-screen')?.classList.contains('active') && currentSubjectId) {
            renderCompleteSubjectSidebar({subjectId:currentSubjectId,id:null});
        }
    }
    async function refresh() {
        if(!isCourseVisible())return;
        const courseId=currentCourseId,batchId=currentBatchId,userId=state.user.id;
        const key=`${userId}:${courseId}:${batchId}`;
        if(pendingKeys.has(key))return;
        pendingKeys.add(key);
        try {
            const data=await apiRequest(`/api/courses/${courseId}/zoom-sessions?batch_id=${batchId}`,'GET',null,{silent:true});
            if(!data.success || Number(data.batchId)!==Number(batchId) || state.user?.id!==userId || currentCourseId!==courseId || currentBatchId!==batchId || !isCourseVisible())return;
            const course=courses.find(item=>Number(item.id)===Number(courseId) && Number(item.batch_id)===Number(batchId));
            if(course)course.zoom_sessions=data.sessions;
            // Time-based 'next class' labels still refresh even if the payload is unchanged.
            renderCourseOverview();
            const signature=JSON.stringify(data.workspaceTopics);
            if(lastAppliedKey!==key || lastPayload!==signature) {
                applyWorkspaceZoom(data.workspaceTopics);
                lastAppliedKey=key;lastPayload=signature;
            }
        } catch(_) {
            // Keep the last successful schedule during temporary connection failures.
        } finally {pendingKeys.delete(key);}
    }
    window.refreshLearnerCourseZoom=refresh;
    setInterval(refresh,15000);
    window.addEventListener('focus',refresh);
    window.addEventListener('online',refresh);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
})();
