
$ = jQuery;
$(document).on('click', '[data--toggle="delete"]', function (e) {
    e.preventDefault();
    let id = $(this).attr('data--id');
    let action = $(this).attr('data--action');

    if (action !== undefined) {

        $.confirm({
            title: 'Are you sure ?',
            content: 'Press yes to delete',
            type: 'red',
            buttons: {
                ok: {
                    text: "Yes",
                    btnClass: 'btn-danger',
                    keys: ['enter'],
                    action: () => {
                        $.ajax({
                            type: 'POST',
                            url: ajaxurl + '?action=ajax_post',
                            dataType: 'json',
                            data: JSON.stringify({
                                id: id,
                                route_name: action
                            }),
                            success: (data) => {

                                if (data.status !== undefined && data.status === true){
                                    if (data.tableReload !== undefined && data.tableReload === true) {
                                        $('#dataTableBuilder').DataTable().ajax.reload();
                                    }

                                    displayMessage(data.message);
                                } else if  (data.status !== undefined && data.status === false) {
                                    displayErrorMessage(data.message);
                                } else {
                                    displayErrorMessage("Internal server error");
                                }

                            },
                            error: (error) => {
                                if (error.responseJSON !== undefined && error.responseJSON.message !== undefined) {
                                    displayErrorMessage(error.responseJSON.message);
                                } else {
                                    displayErrorMessage('Internal server error');
                                }
                            }
                        });
                    }
                },
                cancel: () => {

                }
            }
        });
    }
});


function displayMessage(message) {
    window.Snackbar.show({text: message, pos: 'top-right', duration: 10000});
}

function displayErrorMessage(message) {
    window.Snackbar.show({text: message, pos: 'top-right', backgroundColor : '#f5365c', actionTextColor: '#fff', duration: 10000});
}


function displayAlert (title, message, color = 'red') {
    $.alert({
        title: title,
        content: message,
        type: color,
    });
}

function displayTooltip(object = {}) {
    setTimeout(() => {
        let classElement = object.class !== undefined ? object.class : '.guide';
        window.Tipped.create(classElement, function(element) {
            return {
                content: $(element).data('content')
            };
        },{
            position: object.position !== undefined ? object.position : 'right',
            skin: object.skin !== undefined ? object.skin : 'light',
            size: object.size !== undefined ? object.size : 'large'
        });
    }, 1000);
}
