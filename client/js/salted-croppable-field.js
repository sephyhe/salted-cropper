jQuery.entwine("saltedcroppable", function($) {

    function joinUrl(base, path) {
        if (!base) {
            return path;
        }
        if (!path) {
            return base;
        }
        return base.replace(/\/+$/, '') + '/' + path.replace(/^\/+/, '');
    }

    function getParentFormUrl($input) {
        var form = $input.parents('form').first();
        var formUrl = form.attr('action') || '';
        var formUrlParts = formUrl.split('?');
        return {
            form: form,
            base: formUrlParts[0],
            query: formUrlParts[1]
        };
    }

    function buildFieldActionUrl($input, action, extraQuery) {
        var formData = getParentFormUrl($input);
        var url = joinUrl(formData.base, 'field/' + $input.attr('name') + '/' + action);

        if (extraQuery) {
            url = url + (url.indexOf('?') >= 0 ? '&' : '?') + extraQuery;
        }

        if (typeof formData.query !== 'undefined') {
            url = url + (url.indexOf('?') >= 0 ? '&' : '?') + formData.query;
        }

        return {
            url: url,
            form: formData.form
        };
    }

    function findFieldHolder($response) {
        if ($response.is('.field')) {
            return $response;
        }

        var $field = $response.filter('.field').first();
        if ($field.length) {
            return $field;
        }

        return $response.find('.field').first();
    }

    $("input.croppableimage").entwine({
        Loading: null,
        Dialog: null,
        URL: null,
        ParentForm: null,

        buildActionUrl: function(action, extraQuery) {
            return buildFieldActionUrl(this, action, extraQuery);
        },

        handleSaveResponse: function(response, parentForm) {
            var self = this;
            var $response = $(response);
            var $field = findFieldHolder($response);

            if ($field.length) {
                self.getDialog().empty().dialog("close");
                self.parents('.field:first').replaceWith($field);
                parentForm.addClass('changed');
            } else {
                self.getDialog().html(response);
                self.bindDialogFormSubmit(parentForm);
            }
        },

        submitCroppableImageForm: function($submitForm, parentForm) {
            var self = this;
            var actionUrl = $submitForm.attr('action');

            if (!actionUrl) {
                return;
            }

            var formData = new FormData($submitForm[0]);

            fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.text();
            })
            .then(function(html) {
                self.handleSaveResponse(html, parentForm);
            })
            .catch(function() {
                self.getDialog().find('button.loading').removeClass('loading ui-state-disabled');
            });
        },

        bindDialogFormSubmit: function(parentForm) {
            var self = this;

            this.getDialog().find('form').off('submit.saltedcroppable').on('submit.saltedcroppable', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                self.submitCroppableImageForm($(this), parentForm);
                return false;
            });
        },

        onmatch: function() {
            var self = this;
            var dialog = self.siblings('.salted-croppable-dialog:first');
            this.setDialog(dialog);

            var action = this.buildActionUrl(
                'CroppableImageFormHTML',
                'SaltedCroppableImageID=' + (self.val().length ? self.val() : '0')
            );
            this.setURL(action.url);
            this.setParentForm(action.form);

            this.getDialog().data("field", this).dialog({
                autoOpen: false,
                appendTo: 'body',
                width: $(window).width() * 80 / 100,
                height: $(window).height() * 80 / 100,
                modal: true,
                title: this.data('dialog-title'),
                position: { my: "center", at: "center", of: window }
            });

            this.getDialog().on("click.saltedcroppable", "button", function() {
                $(this).addClass("loading ui-state-disabled");
            });
        },

        onunmatch: function () {
            var self = this;
            this.getDialog().off('.saltedcroppable');
            $('.salted-croppable-dialog.ui-dialog-content').filter(function(){
                return self[0] == $(this).data("field")[0];
            }).remove();
        },

        showDialog: function() {
            var self = this;
            var dlg = this.getDialog();
            var parentForm = this.getParentForm();

            dlg.empty().dialog("open").parent().addClass("loading");

            dlg.load(this.getURL(), function(){
                dlg.parent().removeClass("loading");
                self.bindDialogFormSubmit(parentForm);
            });
        }
    });

    $('div.ss-upload .ss-uploadfield-item-remove, .ss-uploadfield-item-delete').entwine({
        onclick: function(e) {
            var field = this.closest('div.ss-upload'),
                fileupload = field.data('fileupload'),
                item = this.closest('.ss-uploadfield-item'), msg = '';

            if(this.is('.ss-uploadfield-item-delete')) {
                if(confirm(ss.i18n._t('UploadField.ConfirmDelete'))) {
                    this.parents('fieldset:eq(0)').find('.salted-cropper').hide();
                    if (fileupload) {
                        fileupload._trigger('destroy', e, {
                            context: item,
                            url: this.data('href'),
                            type: 'get',
                            dataType: fileupload.options.dataType
                        });
                    }
                }
            } else {
                this.parents('fieldset:eq(0)').find('.salted-cropper').hide();
                if (fileupload) {
                    fileupload._trigger('destroy', e, {context: item});
                }
            }

            e.preventDefault();
            return false;
        }
    });

    $(".croppable-image-field-button").entwine({
        onclick: function(e) {
            e.preventDefault();
            var $input = this.siblings('input.croppableimage');
            if (!$input.length) {
                $input = this.closest('.form__field-holder').find('input.croppableimage');
            }
            $input.showDialog();
            return false;
        },
    });

    $(".croppable-image-field-remove-button").entwine({
        onclick: function(e) {
            e.preventDefault();
            var self = this;
            var id = self.data('id');

            if (confirm('You are going to delete this image. Are you sure')) {
                var $input = self.closest('.form__field-holder').find('input.croppableimage');
                var action = buildFieldActionUrl($input, 'doRemoveCroppableImage', 'id=' + id);
                var holder = self.parents('.field:first');

                self.parents('.form__field-holder:first').html("<img src='framework/images/network-save.gif' />");
                holder.load(action.url, function() {
                    action.form.addClass('changed');
                });
            }

            return false;
        }
    });

    $('.salted-croppable-dialog .uploadfield__upload-button').entwine({
        onmatch: function() {
            var muppet = $(this).parents('.form__field-holder:eq(0)').find('input.entwine-uploadfield');
            var real = $('.dz-input-' + muppet.data('schema').name);
            real.insertAfter(muppet);
        }
    });
});
