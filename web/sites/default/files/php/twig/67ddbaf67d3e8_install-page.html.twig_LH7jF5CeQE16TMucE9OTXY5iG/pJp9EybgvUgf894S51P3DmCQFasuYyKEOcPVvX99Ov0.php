<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* profiles/drupal_cms_installer/theme/templates/install-page.html.twig */
class __TwigTemplate_19e7b3fb7093e993550da41a90413d0c extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 12
        yield "<div class=\"cms-installer\">
  <header class=\"cms-installer__header\">
    <h1 class=\"cms-installer__heading\">
      <img src=\"";
        // line 15
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["theme_path"] ?? null), "html", null, true);
        yield "/images/drupal-cms-logo.svg\" alt=\"";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Drupal CMS"));
        yield "\" />
    </h1>
  </header>
  <div class=\"cms-installer__wrapper\">

    <main class=\"cms-installer__main\">
      ";
        // line 21
        if (($context["title"] ?? null)) {
            // line 22
            yield "        <h2 class=\"cms-installer__main-heading\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["title"] ?? null), "html", null, true);
            yield "</h2>
      ";
        }
        // line 24
        yield "      ";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "highlighted", [], "any", false, false, true, 24), "html", null, true);
        yield "
      ";
        // line 25
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "content", [], "any", false, false, true, 25), "html", null, true);
        yield "
    </main>

    ";
        // line 28
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "sidebar_second", [], "any", false, false, true, 28)) {
            // line 29
            yield "      <aside class=\"cms-installer__sidebar-second\">
        ";
            // line 30
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "sidebar_second", [], "any", false, false, true, 30), "html", null, true);
            yield "
      </aside>
    ";
        }
        // line 33
        yield "
    ";
        // line 34
        if (CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "page_bottom", [], "any", false, false, true, 34)) {
            // line 35
            yield "      <footer class=\"cms-installer__footer\">
        ";
            // line 36
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, ($context["page"] ?? null), "page_bottom", [], "any", false, false, true, 36), "html", null, true);
            yield "
      </footer>
    ";
        }
        // line 39
        yield "  </div>
</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["theme_path", "title", "page"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "profiles/drupal_cms_installer/theme/templates/install-page.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  104 => 39,  98 => 36,  95 => 35,  93 => 34,  90 => 33,  84 => 30,  81 => 29,  79 => 28,  73 => 25,  68 => 24,  62 => 22,  60 => 21,  49 => 15,  44 => 12,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "profiles/drupal_cms_installer/theme/templates/install-page.html.twig", "/var/www/html/web/profiles/drupal_cms_installer/theme/templates/install-page.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 21];
        static $filters = ["escape" => 15, "t" => 15];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                ['if'],
                ['escape', 't'],
                [],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
