<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Contact Data</title>
    <?php
    //call the wp head so  you can get most of your wordpress
    get_header();
    ?>
</head>
<body>

<!--    <div class="wrap">-->
        <form class="form-horizontal" id="contact_data_form">

            <input name="act_group_url" value="<?php echo $refererUrl;?>" style="display: none"/>
            <div class="form-group">
                <label for="input_first_name" class="col-sm-3 control-label"><?php _e('First Name','wordpress.org/plugins/wp2act');?></label>
                <div class="col-sm-5">
                    <input name="firstname" type="text" id="input_first_name" class="form-control" placeholder="<?php _e('First Name','wordpress.org/plugins/wp2act');?>"/>
                </div>
            </div>
            <div class="form-group">
                <label for="input_last_name" class="col-sm-3 control-label"><?php _e('Last Name','wordpress.org/plugins/wp2act');?></label>
                <div class="col-sm-5">
                    <input name="lastname" type="text" id="input_last_name" class="form-control" placeholder="<?php _e('Last Name','wordpress.org/plugins/wp2act');?>"/>
                </div>
            </div>
            <div class="form-group">
                <label for="input_mobilePhone" class="col-sm-3 control-label"><?php _e('Phone','wordpress.org/plugins/wp2act');?></label>
                <div class="col-sm-5">
                    <input name="mobilephone" type="text" id="input_mobilePhone" class="form-control" placeholder="<?php _e('Phone','wordpress.org/plugins/wp2act');?>"/>
                </div>
            </div>
            <div class="form-group">
                <label for="input_emailAddress" class="col-sm-3 control-label"><?php _e('Email','wordpress.org/plugins/wp2act');?></label>
                <div class="col-sm-5">
                    <input name="emailaddress" type="email" id="input_emailAddress" class="form-control" placeholder="<?php _e('Email','wordpress.org/plugins/wp2act');?>"/>
                </div>
            </div>

            <div class="form-group">
                <label for="input_country" class="col-sm-3 control-label"><?php _e('Country','wordpress.org/plugins/wp2act');?></label>
                <div class="col-sm-5">
<!--                    <input name="country" type="text" id="input_country" class="form-control" placeholder="Country"/>-->
                    <select name="country" id="input_country" class="form-control">
                        <?php
                            foreach ($countries as $ck=>$cv){
                                echo '<option value="'.$cv->{'id'}.'='.$cv->{'value'}.'">'.$cv->{'value'}.'</option>';
                            }
                        ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="input_city" class="col-sm-3 control-label"><?php _e('City','wordpress.org/plugins/wp2act');?></label>
                <div class="col-sm-5">
                    <input name="city" type="text" id="input_city" class="form-control" placeholder="<?php _e('City','wordpress.org/plugins/wp2act');?>"/>
                </div>
            </div>
            <div class="form-group">
                <label for="input_street" class="col-sm-3 control-label"><?php _e('Street','wordpress.org/plugins/wp2act');?></label>
                <div class="col-sm-5">
                    <input name="street" type="text" id="input_street" class="form-control" placeholder="<?php _e('Street','wordpress.org/plugins/wp2act');?>"/>
                </div>
            </div>
            <div class="form-group">
                <label for="input_postalCode" class="col-sm-3 control-label"><?php _e('Zip','wordpress.org/plugins/wp2act');?></label>
                <div class="col-sm-5">
                    <input name="postalcode" type="text" id="input_postalCode" class="form-control" placeholder="<?php _e('Zip','wordpress.org/plugins/wp2act');?>"/>
                </div>
            </div>

            <div class="form-group">
                <label for="input_wp2act" class="col-sm-3 control-label"><?php _e('How can we help you','wordpress.org/plugins/wp2act');?></label>
                <div class="col-sm-5">
                    <select name="what_he_wants" id="input_wp2act" class="form-control">
                        <option value="no">No</option>
                        <?php
                        foreach ($items as $ik=>$iv){
                            echo '<option value="'.$iv->{'id'}.'='.$iv->{'value'}.'">'.$iv->{'value'}.'</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="input_comment" class="col-sm-3 control-label"><?php _e('Comment','wordpress.org/plugins/wp2act');?></label>
                <div class="col-sm-5">
                    <textarea name="comment" class="form-control" rows="" cols="" id="input_comment"></textarea>
                </div>
            </div>

            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-10">
                    <p style="color:green;" id="before_send_info"></p>
                </div>
            </div>

            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-10">
                    <button id="crmaddon_contact_btn" type="button" class="btn btn-primary"><?php _e('Submit','wordpress.org/plugins/wp2act');?></button>
                </div>
            </div>
        </form>
<!--    </div>-->
<?php

//call the wp foooter
get_footer();
?>
</body>
</html>