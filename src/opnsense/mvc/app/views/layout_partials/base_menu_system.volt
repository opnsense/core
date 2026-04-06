<aside id="navigation" class="page-side col-xs-12 col-sm-3 col-lg-2 hidden-xs">
    <div class="row">
        <nav class="page-side-nav">
            <div id="mainmenu" class="panel" style="border:0px">
                <div class="panel list-group" style="border:0px">
                    {% for topMenuItem in menuSystem %}
                        {% if topMenuItem.Children|length >= 1 %}
                            <a href="#{{ topMenuItem.Id }}" class="{{ topMenuItem.LinkClass }}" data-toggle="collapse" data-parent="#mainmenu">
                                <span class="{{ topMenuItem.CssClass }} __iconspacer"></span><span style="word-break: keep-all">{{ topMenuItem.VisibleName }}</span>
                            </a>
                            <div class="collapse  {% if topMenuItem.Selected %} active-menu in {% endif  %}" id="{{ topMenuItem.Id }}">
                                {% for subMenuItem in topMenuItem.Children %}
                                    {% if subMenuItem.Url == '' %}
                                    {# next level items, submenu is a container #}
                                        <a href="#{{ topMenuItem.Id }}_{{ subMenuItem.Id }}" class="{{ subMenuItem.LinkClass }}"
                                            data-toggle="collapse" data-parent="#{{ topMenuItem.Id }}">
                                            <div style="display: table;width: 100%;">
                                                <div style="display: table-row">
                                                    <div style="display: table-cell">{{ subMenuItem.VisibleName }}</div>
                                                    <div style="display: table-cell; text-align:right; vertical-align:middle;">
                                                        <span class="{{ subMenuItem.CssClass }}"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                        <div class="collapse {% if subMenuItem.Selected %} active-menu in {% endif  %}" id="{{ topMenuItem.Id }}_{{ subMenuItem.Id }}">
                                            {% for subsubMenuItem in subMenuItem.Children %} {% if subsubMenuItem.IsExternal == "Y" %}
                                            <a href="{{ subsubMenuItem.Url }}" target="_blank" rel="noopener noreferrer" class="{{ subsubMenuItem.LinkClass }}">{{ subsubMenuItem.VisibleName }}</a>
                                            {% elseif acl.isPageAccessible(session.get('Username'),subsubMenuItem.Url) %}
                                            <a href="{{ subsubMenuItem.Url }}" class="{{ subsubMenuItem.LinkClass }}">{{ subsubMenuItem.VisibleName }}</a>
                                            {% endif %} {% endfor %}
                                        </div>
                                    {% elseif subMenuItem.IsExternal == "Y" %}
                                        <a href="{{ subMenuItem.Url }}" target="_blank" rel="noopener noreferrer" class="{{ subMenuItem.LinkClass }}"
                                            aria-expanded="{% if subMenuItem.Selected %}true{%else%}false{% endif  %}">
                                            <div style="display: table;width: 100%;">
                                                <div style="display: table-row">
                                                    <div style="display: table-cell">{{ subMenuItem.VisibleName }}</div>
                                                    <div style="display: table-cell; text-align:right; vertical-align:middle;">
                                                        <span class="{{ subMenuItem.CssClass }}"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    {% elseif acl.isPageAccessible(session.get('Username'),subMenuItem.Url) %}
                                        <a href="{{ subMenuItem.Url }}" class="{{ subMenuItem.LinkClass }}">
                                            <div style="display: table;width: 100%;">
                                                <div style="display: table-row">
                                                    <div style="display: table-cell">{{ subMenuItem.VisibleName }}</div>
                                                    <div style="display: table-cell; text-align:right; vertical-align:middle;">
                                                        <span class="{{ subMenuItem.CssClass }}"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    {% endif %}
                                {% endfor %}
                            </div>
                        {% else %}
                            {# parent level link menu items that pivot #}
                            {% if topMenuItem.IsExternal == "Y" %}
                                <a href="{{ topMenuItem.Url }}" target="_blank" rel="noopener noreferrer" class="{{ topMenuItem.LinkClass }}" data-parent="#mainmenu">
                                    <span class="{{ topMenuItem.CssClass }} __iconspacer"></span>{{ topMenuItem.VisibleName }}
                                </a>
                            {% elseif acl.isPageAccessible(session.get('Username'),topMenuItem.Url) %}
                                <a href="{{ topMenuItem.Url }}" class="{{ topMenuItem.LinkClass }}" data-parent="#mainmenu">
                                    <span class="{{ topMenuItem.CssClass }} __iconspacer"></span>{{ topMenuItem.VisibleName }}
                                </a>
                            {% endif %}
                        {% endif %}
                    {% endfor %}
                </div>
            </div>
        </nav>
    </div>
</aside>
