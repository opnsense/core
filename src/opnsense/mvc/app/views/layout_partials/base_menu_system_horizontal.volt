<nav id="horizontal-menu">
    <button class="hmenu-toggle" aria-label="{{ lang._('Toggle navigation') }}">
        <i class="fa fa-bars"></i>
    </button>
    <ul class="hmenu-nav" id="mainmenu">
        {% for topMenuItem in menuSystem %}
            {% if topMenuItem.Children|length >= 1 %}
                <li class="hmenu-item hmenu-has-children{% if topMenuItem.Selected %} active{% endif %}">
                    <a href="#">
                        <span class="{{ topMenuItem.CssClass }} __iconspacer"></span>{{ topMenuItem.VisibleName }}
                    </a>
                    <ul class="hmenu-dropdown">
                        {% for subMenuItem in topMenuItem.Children %}
                            {% if subMenuItem.Url == '' %}
                                {# sub-container with third-level children #}
                                <li class="hmenu-item hmenu-has-children{% if subMenuItem.Selected %} active{% endif %}">
                                    <a href="#">
                                        {{ subMenuItem.VisibleName }}
                                        <span class="{{ subMenuItem.CssClass }}"></span>
                                    </a>
                                    <ul class="hmenu-flyout">
                                        {% for subsubMenuItem in subMenuItem.Children %}
                                            {% if subsubMenuItem.IsExternal == "Y" %}
                                                <li class="hmenu-item{% if subsubMenuItem.Selected %} active{% endif %}">
                                                    <a href="{{ subsubMenuItem.Url }}" target="_blank" rel="noopener noreferrer">{{ subsubMenuItem.VisibleName }} <i class="fa fa-external-link"></i></a>
                                                </li>
                                            {% elseif acl.isPageAccessible(session.get('Username'),subsubMenuItem.Url) %}
                                                <li class="hmenu-item{% if subsubMenuItem.Selected %} active{% endif %}">
                                                    <a href="{{ subsubMenuItem.Url }}">{{ subsubMenuItem.VisibleName }}</a>
                                                </li>
                                            {% endif %}
                                        {% endfor %}
                                    </ul>
                                </li>
                            {% elseif subMenuItem.IsExternal == "Y" %}
                                <li class="hmenu-item{% if subMenuItem.Selected %} active{% endif %}">
                                    <a href="{{ subMenuItem.Url }}" target="_blank" rel="noopener noreferrer">
                                        {{ subMenuItem.VisibleName }} <i class="fa fa-external-link"></i>
                                    </a>
                                </li>
                            {% elseif acl.isPageAccessible(session.get('Username'),subMenuItem.Url) %}
                                <li class="hmenu-item{% if subMenuItem.Selected %} active{% endif %}">
                                    <a href="{{ subMenuItem.Url }}">
                                        {{ subMenuItem.VisibleName }}
                                    </a>
                                </li>
                            {% endif %}
                        {% endfor %}
                    </ul>
                </li>
            {% else %}
                {# top-level link (no children) #}
                {% if topMenuItem.IsExternal == "Y" %}
                    <li class="hmenu-item{% if topMenuItem.Selected %} active{% endif %}">
                        <a href="{{ topMenuItem.Url }}" target="_blank" rel="noopener noreferrer">
                            <span class="{{ topMenuItem.CssClass }} __iconspacer"></span>{{ topMenuItem.VisibleName }} <i class="fa fa-external-link"></i>
                        </a>
                    </li>
                {% elseif acl.isPageAccessible(session.get('Username'),topMenuItem.Url) %}
                    <li class="hmenu-item{% if topMenuItem.Selected %} active{% endif %}">
                        <a href="{{ topMenuItem.Url }}">
                            <span class="{{ topMenuItem.CssClass }} __iconspacer"></span>{{ topMenuItem.VisibleName }}
                        </a>
                    </li>
                {% endif %}
            {% endif %}
        {% endfor %}
    </ul>
</nav>
