const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const control = (id, text, allowed = false) => ({
    id, textContent:text, hidden:false,
    style:{setProperty(){this.display='none';}},
    closest:()=>allowed ? {} : null,
    getAttribute:()=>null,
});
const confirm = control('confirmDeleteBtn', 'Delete');
const forbidden = control('', 'Delete course');
const permitted = control('', 'Delete subtopic', true);
const document = {
    body:{},
    querySelectorAll:selector=>selector==='form' ? [] : [confirm, forbidden, permitted],
};
vm.runInNewContext(fs.readFileSync('public/assistant-admin.js','utf8'), {
    document, MutationObserver:class {observe(){}},
});
assert.equal(confirm.hidden, false);
assert.equal(permitted.hidden, false);
assert.equal(forbidden.hidden, true);
console.log('Assistant admin can see shared delete confirmation; restricted deletion controls stay hidden.');
