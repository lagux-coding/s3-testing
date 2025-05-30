window.S3 = window.S3 || {}
window.S3.States = window.S3.States || {};

(
    function (S3) {
        var makeConstant = S3.Functions.makeConstant

        S3.States = Object.create({}, {
            DONE: makeConstant('done'),
            DOWNLOADING: makeConstant('downloading'),
        })

        Object.freeze(S3.States)
    } (S3)
)