

<h1> OPNsense sample module </h1>

A simple input form for the "sample" model can be found  <a href="page/">here </a><br/>

<hr/>

To perform a call to the api, press this button : <br/>

fill in a message : <input type="text" value="" id="msg"> </br>
<input type="button" id="restcall" value="do REST call!"/>



<br/>

API call result : <div id="msgid"></div>


<script type="text/javascript">

    $( "#restcall" ).click( function() {
        $.ajax({
            type: "PUT",
            url: "/api/sample/",
            success: function(data){
                $("#msgid").html( data.message );
            },
            data:{message:$("#msg").val()}
        });

    });

</script>

