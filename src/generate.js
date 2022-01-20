const {createDoc} = require('apidoc');
const {data, project} = createDoc({
    src: ['routes'],
    dest: 'public/doc',
    silent: true,
    dryRun: false,
    warnError: true
});
console.log(JSON.stringify({data: JSON.parse(data), project: JSON.parse(project)}));
