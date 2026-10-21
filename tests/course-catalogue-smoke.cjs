const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync('public/script.js', 'utf8');
const selection = source.slice(source.indexOf('let visibleCourses ='), source.indexOf("if (state.courseListFilter === 'available') {", source.indexOf('let visibleCourses =')));
const courses = [{id:1,title:'PNLE',is_enrolled:true},{id:2,title:'NCLEX',is_enrolled:false}];
for (const filter of ['available','enrolled']) {
    const context = {courses,state:{courseListFilter:filter}};
    vm.createContext(context);
    vm.runInContext(selection + '; result = visibleCourses;', context);
    assert.deepStrictEqual(Array.from(context.result, course => course.id), filter === 'available' ? [1,2] : [1]);
}
assert(source.includes("state.courseListFilter === 'available' ? 'Currently Enrolled' : 'Active Enrollment'"));
console.log('All Courses includes enrolled courses; My Courses stays enrollment-only and catalogue cards identify current enrollment.');
