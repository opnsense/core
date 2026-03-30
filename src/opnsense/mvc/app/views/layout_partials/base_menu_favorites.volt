{# Favorites section - only show if user has favorites #}
<a href="#Favorites" id="favorites-header" class="list-group-item" data-toggle="collapse" data-parent="#mainmenu"{% if not menuHasFavorites %} style="display:none;"{% endif %}>
    <span class="fas fa-star __iconspacer"></span><span style="word-break: keep-all">{{ lang._('Favorites') }}</span>
</a>
<div class="collapse" id="Favorites">
    {% for favItem in menuFavorites %}
        {% if favItem.IsExternal == "Y" %}
            <a href="{{ favItem.Url }}" target="_blank" rel="noopener noreferrer" class="list-group-item" data-menu-url="{{ favItem.Url }}">
                {{ favItem.Label }}
            </a>
        {% elseif acl.isPageAccessible(session.get('Username'), favItem.Url) %}
            <a href="{{ favItem.Url }}" class="list-group-item" data-menu-url="{{ favItem.Url }}">
                {{ favItem.Label }}
            </a>
        {% endif %}
    {% endfor %}
</div>
