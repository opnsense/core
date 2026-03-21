{# Favorites section - only show if user has favorites #}
<a href="#Favorites" id="favorites-header" class="list-group-item" data-toggle="collapse" data-parent="#mainmenu"{% if not menuHasFavorites %} style="display:none;"{% endif %}>
    <span class="fas fa-star __iconspacer"></span><span style="word-break: keep-all">{{ lang._('Favorites') }}</span>
</a>
<div class="collapse" id="Favorites">
    {% for topMenuItem in menuSystem %}
        {% if topMenuItem.IsFavorite and topMenuItem.Url != '' %}
            {% if topMenuItem.IsExternal == "Y" %}
                <a href="{{ topMenuItem.Url }}" target="_blank" rel="noopener noreferrer" class="list-group-item" data-menu-url="{{ topMenuItem.Url }}">
                    {{ topMenuItem.VisibleName | striptags }}
                </a>
            {% elseif acl.isPageAccessible(session.get('Username'),topMenuItem.Url) %}
                <a href="{{ topMenuItem.Url }}" class="list-group-item" data-menu-url="{{ topMenuItem.Url }}">
                    {{ topMenuItem.VisibleName | striptags }}
                </a>
            {% endif %}
        {% endif %}
        {% if topMenuItem.Children|length >= 1 %}
            {% for subMenuItem in topMenuItem.Children %}
                {% if subMenuItem.IsFavorite and subMenuItem.Url != '' %}
                    {% if subMenuItem.IsExternal == "Y" %}
                        <a href="{{ subMenuItem.Url }}" target="_blank" rel="noopener noreferrer" class="list-group-item" data-menu-url="{{ subMenuItem.Url }}">
                            {{ topMenuItem.VisibleName | striptags }}: {{ subMenuItem.VisibleName | striptags }}
                        </a>
                    {% elseif acl.isPageAccessible(session.get('Username'),subMenuItem.Url) %}
                        <a href="{{ subMenuItem.Url }}" class="list-group-item" data-menu-url="{{ subMenuItem.Url }}">
                            {{ topMenuItem.VisibleName | striptags }}: {{ subMenuItem.VisibleName | striptags }}
                        </a>
                    {% endif %}
                {% endif %}
                {% if subMenuItem.Children|length >= 1 %}
                    {% for subsubMenuItem in subMenuItem.Children %}
                        {% if subsubMenuItem.IsFavorite and subsubMenuItem.Url != '' %}
                            {% if subsubMenuItem.IsExternal == "Y" %}
                                <a href="{{ subsubMenuItem.Url }}" target="_blank" rel="noopener noreferrer" class="list-group-item" data-menu-url="{{ subsubMenuItem.Url }}">
                                    {{ topMenuItem.VisibleName | striptags }}: {{ subMenuItem.VisibleName | striptags }}: {{ subsubMenuItem.VisibleName | striptags }}
                                </a>
                            {% elseif acl.isPageAccessible(session.get('Username'),subsubMenuItem.Url) %}
                                <a href="{{ subsubMenuItem.Url }}" class="list-group-item" data-menu-url="{{ subsubMenuItem.Url }}">
                                    {{ topMenuItem.VisibleName | striptags }}: {{ subMenuItem.VisibleName | striptags }}: {{ subsubMenuItem.VisibleName | striptags }}
                                </a>
                            {% endif %}
                        {% endif %}
                    {% endfor %}
                {% endif %}
            {% endfor %}
        {% endif %}
    {% endfor %}
</div>
