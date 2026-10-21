let classZoomPlacementCourses=[];
function zoomPlacementOptions(select, items, placeholder, value) {
    select.replaceChildren(new Option(placeholder,''));
    items.forEach(item=>select.add(new Option(item.title,item.id)));
    select.value=String(value||'');
}
function setClassZoomPlacementCourses(courses) {
    const courseId=document.getElementById('class_zoom_course').value;
    const subjectId=document.getElementById('class_zoom_subject').value;
    const topicId=document.getElementById('class_zoom_topic').value;
    classZoomPlacementCourses=courses;
    selectClassZoomPlacement({courseId,subjectId,topicId});
}
function selectClassZoomPlacement(item) {
    zoomPlacementOptions(document.getElementById('class_zoom_course'),classZoomPlacementCourses,'Select master course',item.courseId);
    refreshClassZoomSubjects(item.subjectId,item.topicId);
}
function refreshClassZoomSubjects(subjectId,topicId) {
    const course=classZoomPlacementCourses.find(item=>String(item.id)===document.getElementById('class_zoom_course').value);
    zoomPlacementOptions(document.getElementById('class_zoom_subject'),course?.subjects||[],'Select subject',subjectId);
    refreshClassZoomTopics(topicId);
}
function refreshClassZoomTopics(topicId) {
    const course=classZoomPlacementCourses.find(item=>String(item.id)===document.getElementById('class_zoom_course').value);
    const subject=course?.subjects.find(item=>String(item.id)===document.getElementById('class_zoom_subject').value);
    zoomPlacementOptions(document.getElementById('class_zoom_topic'),subject?.topics||[],'Zoom Links (batch-only default)',topicId);
}
document.getElementById('class_zoom_course').addEventListener('change',()=>refreshClassZoomSubjects());
document.getElementById('class_zoom_subject').addEventListener('change',()=>refreshClassZoomTopics());
