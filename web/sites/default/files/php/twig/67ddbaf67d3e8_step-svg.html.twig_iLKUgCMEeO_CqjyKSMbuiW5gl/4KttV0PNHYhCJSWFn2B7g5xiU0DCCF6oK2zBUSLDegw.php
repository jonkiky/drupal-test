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

/* profiles/drupal_cms_installer/theme/templates/step-svg.html.twig */
class __TwigTemplate_0bdd0a58bc59ca7485951d1a8a0c1c37 extends Template
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
        // line 1
        yield "<div id=\"svg-step-";
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["id"] ?? null), "html", null, true);
        yield "\" class=\"cms-installer__pattern\" aria-hidden=\"true\" style=\"display: none;\">
  ";
        // line 2
        yield from $this->loadTemplate(((($this->extensions['Drupal\Core\Template\TwigExtension']->getActiveThemePath() . "/images/step-") . ($context["id"] ?? null)) . ".svg"), "profiles/drupal_cms_installer/theme/templates/step-svg.html.twig", 2)->unwrap()->yield($context);
        // line 3
        yield "</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["id"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "profiles/drupal_cms_installer/theme/templates/step-svg.html.twig";
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
        return array (  51 => 3,  49 => 2,  44 => 1,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "profiles/drupal_cms_installer/theme/templates/step-svg.html.twig", "/var/www/html/web/profiles/drupal_cms_installer/theme/templates/step-svg.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["include" => 2];
        static $filters = ["escape" => 1];
        static $functions = ["active_theme_path" => 2];

        try {
            $this->sandbox->checkSecurity(
                ['include'],
                ['escape'],
                ['active_theme_path'],
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
