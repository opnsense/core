

<button class="btn btn-default"  id="restcall" type="button">do REST call!</button>
<br/>

API call result : <div id="msgid"></div>


<script type="text/javascript">

    $( "#restcall" ).click( function() {
        $.ajax({
            type: "POST",
            url: "/api/proxy/service/status",
            success: function(data){
                $("#msgid").html( data.status );
            },
            data:{}
        });
    });

</script>
