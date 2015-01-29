<div>
<i>this is a sample page for the OPNsense mvc frontend framework</i>
</div>

{{ sample.tag1 }}

 <form action="save" method="post">
  <input type="text" name="sample.{{sample.tag1.__reference}}" value="{{sample.tag1}}"><br>
  <input type="text" name="sample.{{sample.tagX.tag1.__reference}}" value="{{sample.tagX.tag1}}"><br>

  <input type="submit" value="Submit">
</form>