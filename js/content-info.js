
jQuery(document).ready( function($) {
    $('#crmaddon_contact_btn').click(function () {
        if(confirm("Do you want to submit the information you have filled?")){
            var contact_form = $('#contact_data_form').serializeArray();
            $.ajax({
                type:"POST",
                data:{'contactInfo':contact_form,'action':'ajax_send_contact_data'},
                url: ajax_object.ajax_url,
                beforeSend: function() {
                    var html = '<span style="color: green">Sending......</span>';
                    $('#before_send_info').html(html);
                },
                success: function( $data ) {
                    $('#before_send_info').html($data);
                },
                error:function () {
                    $('#before_send_info').css('color','red');
                    $('#before_send_info').html('Fail to send the contact information,Please refresh the page an try again!');
                }

            });
        }else{
            console.log("cancel the operation");
        }
    });


    /**
     * check the act configuration info
     */
    $('#check_act_configuration').click(function () {
        var username = $('#act_username').val();
        var password = $('#act_password').val();
        var database = $('#act_database').val();
        var url = $('#act_url').val();
        var urlEndStr = 'act.web.api';


        if(username == '' || database == '' || url == ''){
            $('#error_url').html('Please input the Username,Database and Url');
        }else{
            if(url.substr(url.length-urlEndStr.length,urlEndStr.length) == urlEndStr){
                actCheckTool(username,password,database,url);
            }else{
                if(confirm("The URL should ends with act.web.api,do you want to continue to test connection and store it?")){
                    actCheckTool(username,password,database,url);
                }else{
                    console.log('Cancel the operation!');
                }
            }
        }
    });


    function actCheckTool(username,password,database,url)
    {
        var responseCode = {
            401:'Unauthorized indicates that the requested resource requires authentication.',
            403:'Forbidden indicates that the user does not have the necessary permissions for the resource.',
            4030:'Incompatibility issue with Act!',
            4031:'Subscription required.',
            4032:'API access permission required.',
        };

        $.ajax({
            type:"POST",
            data:{'username':username,'password':password,'database':database,'url':url,'action':'check_act_configuration'},
            url: ajax_object.ajax_url,
            beforeSend: function() {
                $('#error_url').css('color','green');
                $('#error_url').html('Check......');
            },
            success: function( $data ) {
                var linkInfo = eval('(' + $data + ')');
                var code = linkInfo.response.code;
                if(code == 200){
                    $('#error_url').css('color','green');
                    $('#error_url').html("Successful to link the ACT Service,you can click 'Save Changes' button and store your configuration now");
                }else if(code == 401 || code == 403 || code == 4030 || code == 4031 || code == 4032){
                    $('#error_url').css('color','red');
                    $('#error_url').html(responseCode[code]);
                }else{
                    $('#error_url').css('color','red');
                    $('#error_url').html('There maybe something wrong about the network or ACT service, please check again!');
                }
            },
            error:function () {
                $('#error_url').css('color','red');
                $('#error_url').html('There maybe something wrong about the network or ACT service, please check again!');
            }

        });
    }





    
});


