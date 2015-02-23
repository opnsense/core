<aside id="navigation" class="page-side col-xs-12 col-sm-2 hidden-xs">
    <div class="row">
        <nav class="page-side-nav" role="navigation">
            <div id="mainmenu" class="panel">
                {% for topMenuItem in menuSystem %}
                <div class="panel list-group">
                    <a href="#{{ topMenuItem.Id }}" class="list-group-item {% if topMenuItem.Selected %}  active-menu-title {% endif  %}" data-toggle="collapse" data-parent="#mainmenu"><span class="{{ topMenuItem.CssClass }} __iconspacer"></span>{{ topMenuItem.VisibleName }} </a>
                    <div class="collapse  {% if topMenuItem.Selected %} active-menu in {% endif  %}" id="{{ topMenuItem.Id }}">
                        {% for subMenuItem in topMenuItem.Children %}
                            {% if acl.isPageAccessible(session.get('Username'),subMenuItem.Url)  %}
                                <a href="{{ subMenuItem.Url }}" class="list-group-item {% if subMenuItem.Selected %} active {% endif  %}">{{ subMenuItem.VisibleName }}</a>
                            {% endif %}
                        {% endfor %}
                    </div>
                </div>
                {% endfor %}
            </div>
        </nav>
    </div>
</aside>
