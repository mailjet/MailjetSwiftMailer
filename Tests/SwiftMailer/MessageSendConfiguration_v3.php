<?php
namespace Mailjet\MailjetSwiftMailer\Tests\SwiftMailer;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * A configuration matching the schema of a message send API call
 *
 * https://dev.mailjet.com/guides/#send-api-json-properties
 */
class MessageSendConfiguration_v3 implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('message');
        $rootNode->children()->scalarNode('FromEmail')->isRequired();
        $rootNode->children()->scalarNode('FromName');
        $rootNode->children()->scalarNode('Sender');
        $this->buildRecipientsSection($rootNode);
        $rootNode->children()->scalarNode('To');
        $rootNode->children()->scalarNode('Cc');
        $rootNode->children()->scalarNode('Bcc');
        $rootNode->children()->scalarNode('Subject')->isRequired();
        $rootNode->validate()->ifTrue(function (array $message) {
            $html = (isset($message['Html-part']) ? true : false);
            $text = (isset($message['Text-part']) ? true : false);
            $templateId = (isset($message['Mj-TemplateID']) ? true : false);
            return ($html || $text || $templateId);
        })->thenInvalid('Html-part or Text-part must be provided');
        $rootNode->children()->scalarNode('Html_part');
        $rootNode->children()->scalarNode('Text_part');
        $rootNode->children()->scalarNode('Mj_TemplateID');
        $rootNode->children()->booleanNode('Mj_TemplateLanguage');
        $rootNode->children()->scalarNode('Mj_TemplateErrorReporting');
        $rootNode->children()->scalarNode('Mj_TemplateErrorDeliver');
        $this->buildAttachmentsSection($rootNode);
        $this->buildInlineAttachmentsSection($rootNode);
        $rootNode->children()->integerNode('Mj_prio');
        $rootNode->children()->scalarNode('Mj_campaign');
        $rootNode->children()->booleanNode('Mj_deduplicatecampaign');
        $rootNode->children()->integerNode('Mj_trackopen');
        $rootNode->children()->integerNode('Mj_trackclick');
        $rootNode->children()->scalarNode('Mj_CustomID');
        $rootNode->children()->scalarNode('Mj_EventPayLoad');
        $rootNode->children()->arrayNode('Headers')->ignoreExtraKeys();
        $this->buildVarsSection($rootNode);
        // for bulk: https://dev.mailjet.com/guides/#sending-in-bulk
        $rootNode->children()->arrayNode('Messages')->ignoreExtraKeys();

        return $treeBuilder;
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function buildRecipientsSection(ArrayNodeDefinition $rootNode)
    {
        /** @var ArrayNodeDefinition $metadataNode */
        $metadataNode = $rootNode->children()->arrayNode('Recipients')->prototype('array');
        $metadataNode->children()->scalarNode('Email')->isRequired();
        $metadataNode->children()->scalarNode('Name');
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function buildVarsSection(ArrayNodeDefinition $rootNode)
    {
        /** @var ArrayNodeDefinition $mergeNode */
        $mergeNode = $rootNode->children()->arrayNode('Vars')->ignoreExtraKeys();
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function buildAttachmentsSection(ArrayNodeDefinition $rootNode)
    {
        /** @var ArrayNodeDefinition $attachmentsNode */
        $attachmentsNode = $rootNode->children()->arrayNode('Attachments')->prototype('array');
        $attachmentsNode->children()->scalarNode('Content_type')->isRequired();
        $attachmentsNode->children()->scalarNode('Filename')->isRequired();
        $attachmentsNode->children()->scalarNode('content')->isRequired();
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function buildInlineAttachmentsSection(ArrayNodeDefinition $rootNode)
    {
        /** @var ArrayNodeDefinition $attachmentsNode */
        $attachmentsNode = $rootNode->children()->arrayNode('Inline_attachments')->prototype('array');
        $attachmentsNode->children()->scalarNode('Content_type')->isRequired();
        $attachmentsNode->children()->scalarNode('Filename')->isRequired();
        $attachmentsNode->children()->scalarNode('content')->isRequired();
    }
}