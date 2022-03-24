const {createDoc} = require('apidoc');

const {data, project} = createDoc({
    src      : ['routes'],
    dest     : 'public/doc',
    silent   : true,
    dryRun   : false,
    warnError: true,
    hooks    : {
        'parser-find-elements': [
            {
                func    : require('./CreateDocFromJson'),
                priority: 200
            }
        ]
    },
});
console.log(JSON.stringify({data: JSON.parse(data), project: JSON.parse(project)}));
