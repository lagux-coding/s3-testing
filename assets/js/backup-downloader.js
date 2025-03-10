window.S3 = window.S3 || {};

(
    function ($, _, S3, ajaxurl, tbRemove) {
        var downloader;
        var Downloader;

        //check
        if(!ajaxurl) {
            console.warn('Missing ajaxurl value.');
            return;
        }

        if(!('EventSource' in window)) {
            console.warn('Event Source does not exist in this browser');
            return;
        }

        function destruct() {
            this.closeEventSource();
            this.cleanUi();
        }

        function hideElement(el)
        {
            if(!el) {
                return;
            }
            el.style.display = "none";
        }

        function showElement(el)
        {
            if(!el) {
                return;
            }
            el.style.display = "block";
        }

        //object Downloader
        Downloader = {
            init: function()
            {
                this.addListeners();
                return this;
            },

            construct: function()
            {
                var containerUi = document.querySelector("#tb_container");
                if(!containerUi) {
                    return false;
                }

                _.bindAll(
                    this,
                    "init",
                    "addListeners",
                    "startDownload",
                    "closeEventSource",
                    "showWaitingMessage",
                    "initializeEventSource",
                    "done",
                    "onMessage",
                    "onError",
                    "showProgressUi",
                    "showSuccessMsg",
                    "hideWaitingMessage",
                    "hideProgressUi",
                    "hideSuccessMsg",
                    "cleanUi",
                );

                this.containerUi = containerUi;
                this.waitingUi = this.containerUi.querySelector("#download-file-waiting");
                this.progressUi = this.containerUi.querySelector(".progressbar");
                this.successUi = this.containerUi.querySelector("#download-file-success");

                this.currentTarget = undefined;
                this.eventSource = undefined;

                return this;
            },

            //event listeners
            addListeners: function()
            {
                _.forEach(
                    document.querySelectorAll("#backup-download-link"),
                    function (downloadlink) {
                        downloadlink.addEventListener("click", this.startDownload);
                    }.bind(this)
                );
                $("body").on(
                    "thickbox:removed",
                    function () {
                        destruct.call(this);
                    }.bind(this)
                );
                return this;
            },

            //start download
            startDownload: function(evt)
            {
                evt.preventDefault();
                this.currentTarget = evt.target;

                this.showWaitingMessage();
                this.initializeEventSource();
            },

            closeEventSource: function () {
                if (_.isUndefined(this.eventSource)) {
                    return;
                }

                this.eventSource.close();
                this.eventSource = undefined;
            },

            initializeEventSource: function () {
                if(!_.isUndefined(this.eventSource)) {
                    return;
                }

                this.eventSource = new EventSource(
                    ajaxurl +
                    "?action=download_backup&destination=" + this.currentTarget.dataset.destination +
                    "&jobid=" + this.currentTarget.dataset.jobid +
                    "&file=" + this.currentTarget.dataset.file +
                    "&s3testing_action_nonce=" + this.currentTarget.dataset.nonce
                );

                //message
                this.eventSource.onmessage = this.onMessage;
                this.eventSource.addEventListener("log", this.onError);
            },

            onMessage: function (message) {
                var data;

                try {
                    data = JSON.parse(message.data);

                    switch (data.state) {
                        case S3.States.DOWNLOADING:
                            this.cleanUi();
                            this.showProgressUi();

                            $("#progresssteps")
                                .css({
                                    width: data.download_percent + "%",
                                })
                                .text(data.download_percent + "%");
                            break;

                        case S3.States.DONE:
                            this.done(data.message);
                            break;
                    }
                } catch (exc) {
                    S3.Functions.printMessageError(exc.message, this.containerUi);
                    destruct.call(this);
                }
            },

            onError: function (message) {
                var data = JSON.parse(message.data);

                this.closeEventSource();

                switch (data.message) {
                    default:
                        S3.Functions.printMessageError(data.message, this.containerUi);
                        destruct.call(this);
                        break;
                }

                return this;
            },

            done: function () {
                this.showSuccessMsg();
                window.location.href = this.currentTarget.dataset.url;

                setTimeout(tbRemove, 3000);
            },

            //some function
            showWaitingMessage: function () {
                showElement(this.waitingUi);
            },
            showProgressUi: function () {
                showElement(this.progressUi);
            },
            showSuccessMsg: function () {
                showElement(this.successUi);
            },

            hideWaitingMessage: function () {
                hideElement(this.waitingUi);
            },
            hideProgressUi: function () {
                hideElement(this.progressUi);
            },
            hideSuccessMsg: function () {
                hideElement(this.successUi);
            },

            cleanUi: function () {
                this.hideWaitingMessage();
                this.hideProgressUi();
                this.hideSuccessMsg();
            },
        };


        //init
        downloader = Object.create(Downloader);

        if(downloader.construct()) {
            downloader.init();
        }
    }
) (window.jQuery, window._, window.S3, window.ajaxurl, window.tb_remove);