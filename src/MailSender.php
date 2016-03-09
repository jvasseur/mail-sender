<?php

namespace Jvasseur\MailSender;

class MailSender
{
    /**
     * @var \Swift_Mailer
     */
    private $mailer;

    /**
     * @var \Twig_Environment
     */
    private $twig;

    public function __construct(\Swift_Mailer $mailer, \Twig_Environment $twig)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

    /**
     * @param string $name
     * @param array  $context
     * @param callable|null $callback a callback to modify the mail before it is sent.
     */
    public function send($name, array $context = [], callable $callback = null)
    {
        $template = $this->twig->loadTemplate($name);

        $blocks = [];
        foreach (['from', 'from_name', 'to', 'to_name', 'subject', 'body_txt', 'body_html'] as $blockName) {
            $rendered = $this->renderBlock($template, $blockName, $context);

            if ($rendered) {
                $blocks[$blockName] = $rendered;
            }
        }

        $blocks = array_merge($context, $blocks);

        $mail = new \Swift_Message();
        $mail->setSubject($blocks['subject']);
        $mail->setFrom(isset($blocks['from_name']) ? [$blocks['from'] => $blocks['from_name']] : $blocks['from']);

        if (isset($blocks['to'])) {
            $mail->setTo(isset($blocks['to_name']) ? [$blocks['to'] => $blocks['to_name']] : $blocks['to']);
        }

        if (isset($blocks['body_txt']) && isset($blocks['body_html'])) {
            $mail->setBody($blocks['body_txt']);
            $mail->addPart($blocks['body_html'], 'text/html');
        } elseif (isset($blocks['body_txt'])) {
            $mail->setBody($blocks['body_txt']);
        } elseif (isset($blocks['body_html'])) {
            $mail->setBody($blocks['body_html'], 'text/html');
        }

        if ($callback) {
            $callback($mail);
        }

        $this->mailer->send($mail);
    }

    /**
     * Renders a Twig block.
     *
     * see {@link https://github.com/twigphp/Twig/issues/676#issuecomment-15842093}
     *
     * @param \Twig_Template $template
     * @param string         $block
     * @param array          $context
     *
     * @return string
     *
     * @throws \Exception
     */
    private function renderBlock(\Twig_Template $template, $block, array $context)
    {
        $context = $template->getEnvironment()->mergeGlobals($context);

        $level = ob_get_level();
        ob_start();
        try {
            $rendered = $template->renderBlock($block, $context);
            ob_end_clean();

            return $rendered;
        } catch (\Exception $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }
    }
}
