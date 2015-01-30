<style type="text/css">
.tg  {border-collapse:collapse;border-spacing:0;border-color:#aabcfe;}
.tg td{font-family:Arial, sans-serif;font-size:14px;padding:10px 5px;border-style:solid;border-width:0px;overflow:hidden;word-break:normal;border-color:#aabcfe;color:#669;background-color:#e8edff;border-top-width:1px;border-bottom-width:1px;}
.tg th{font-family:Arial, sans-serif;font-size:14px;font-weight:normal;padding:10px 5px;border-style:solid;border-width:0px;overflow:hidden;word-break:normal;border-color:#aabcfe;color:#039;background-color:#b9c9fe;border-top-width:1px;border-bottom-width:1px;}
.tg .tg-vn4c{background-color:#D2E4FC}
</style>

<div style="width:600px">
    This a sample page for the OPNsense MVC framework. This page demonstrates how to create a model (OPNsense\Sample\Sample.xml) and bind this to data in our config.xml.
    <br/><br/>
    The index controller (controllers\OPNsense\Sample\PageController) binds some data to this form like the title ({{title|default("'no title set'") }}).
    and the actual data from the Sample model, which is a combination of the data presented in the config.xml and the defaults set in the model xml.

    <br/><br/>
    When errors occur while saving this form, they will be shown below:

    {% for error_message in error_messages %}
        <i style="color:red"> {{ error_message['field'] }} : {{ error_message['msg'] }} </i> <br>
    {% endfor %}

    <br/><br/>
    Edit the data 

</div>


<form action="save" method="post">
    <table class="tg">
        <tr>
            <td>{{ lang._('item') }} </td>
            <td>{{ lang._('internal reference') }}</td>
            <td>{{ lang._('input') }}</td>
        </tr>

        <tr>
            <td>tag1 </td>
            <td>{{sample.tag1.__reference}}</td>
            <td>
                {# for demonstration purposes, use a partial for this field #}
                {{ partial("layout_partials/sample_input_field", ['field_type': 'text','field_content':sample.tag1,'field_name':sample.tag1.__reference,'field_name_prefix':'sample.']) }}
            </td>
        </tr>

        <tr>
            <td>tagX/tag1 </td>
            <td>{{sample.tagX.tag1.__reference}}</td>
            <td><input type="text" name="sample.{{sample.tagX.tag1.__reference}}" value="{{sample.tagX.tag1}}"><br><br></td>
        </tr>

        <tr> <td colspan=3>detail items within sample model</td> </tr>
        {# traverse details #}
        {% for section_item in sample.childnodes.section.__items %}
            <tr> <td colspan=3>##ROW## delete node {{section_item.__reference}} <input type="checkbox" name="delete[{{section_item.__reference}}]" value="1"> </td> </tr>
            <tr>
                <td>childnodes/section/[]/node1</td>
                <td>{{section_item.node1.__reference}}</td>
                <td><input type="text" name="sample.{{section_item.node1.__reference}}" value="{{section_item.node1}}"></td>
            </tr>

            <tr>
                <td>childnodes/section/[]/node2</td>
                <td>{{section_item.node2.__reference}}</td>
                <td><input type="text" name="sample.{{section_item.node2.__reference}}" value="{{section_item.node2}}"></td>
            </tr>
        {% endfor %}
            <tr>
                <td></td>
                <td></td>
                <td><input type="submit" value="add" name="form_action" ></td>
            </tr>

        <tr> <td colspan=3> </td> </tr>
        <tr> <td colspan=3><input type="submit" value="save" name="form_action" > </td> </tr>
    </table>

</form>