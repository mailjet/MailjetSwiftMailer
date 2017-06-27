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
class MessageSendConfiguration_v31 implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('Messages');
        $this->buildFromSection($rootNode);
        $rootNode->children()->scalarNode('Sender');
        $this->buildToSection($rootNode);
        $this->buildCcSection($rootNode);
        $this->buildBccSection($rootNode);
        $rootNode->children()->scalarNode('Subject')->isRequired();
        $rootNode->children()->scalarNode('HTMLPart');
        $rootNode->children()->scalarNode('TextPart');
        $rootNode->children()->scalarNode('TemplateID');
        $rootNode->children()->booleanNode('TemplateLanguage');
        $rootNode->children()->scalarNode('TemplateErrorReporting');
        $rootNode->children()->scalarNode('TemplateErrorDeliver');
        $this->buildAttachmentsSection($rootNode);
        $this->buildInlineAttachmentsSection($rootNode);
        $rootNode->children()->integerNode('Priority');
        $rootNode->children()->scalarNode('CustomCampaign');
        $rootNode->children()->booleanNode('DeduplicateCampaign');
        $rootNode->children()->integerNode('TrackOpens');
        $rootNode->children()->integerNode('TrackClicks');
        $rootNode->children()->scalarNode('CustomID');
        $rootNode->children()->scalarNode('EventPayload');
        $rootNode->children()->scalarNode('MonitoringCategory');
        $rootNode->children()->arrayNode('Headers')->ignoreExtraKeys();
        $this->buildVarsSection($rootNode);
        $this->buildReplyToSection($rootNode);
        // for bulk: https://dev.mailjet.com/guides/#sending-in-bulk
        $rootNode->children()->arrayNode('Messages')->ignoreExtraKeys();

        return $treeBuilder;
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function buildFromSection(ArrayNodeDefinition $rootNode)
    {
        /** @var ArrayNodeDefinition $metadataNode */
        $metadataNode = $rootNode->children()->arrayNode('From')->isRequired();
        $metadataNode->children()->scalarNode('Email')->isRequired();
        $metadataNode->children()->scalarNode('Name');
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function buildToSection(ArrayNodeDefinition $rootNode)
    {
        /** @var ArrayNodeDefinition $metadataNode */
        $metadataNode = $rootNode->children()->arrayNode('To')->prototype('array');
        $metadataNode->children()->scalarNode('Email')->isRequired();
        $metadataNode->children()->scalarNode('Name');
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function buildCcSection(ArrayNodeDefinition $rootNode)
    {
        /** @var ArrayNodeDefinition $metadataNode */
        $metadataNode = $rootNode->children()->arrayNode('Cc')->prototype('array');
        $metadataNode->children()->scalarNode('Email')->isRequired();
        $metadataNode->children()->scalarNode('Name');
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function buildBccSection(ArrayNodeDefinition $rootNode)
    {
        /** @var ArrayNodeDefinition $metadataNode */
        $metadataNode = $rootNode->children()->arrayNode('Bcc')->prototype('array');
        $metadataNode->children()->scalarNode('Email')->isRequired();
        $metadataNode->children()->scalarNode('Name');
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function buildReplyToSection(ArrayNodeDefinition $rootNode)
    {
        /** @var ArrayNodeDefinition $metadataNode */
        $metadataNode = $rootNode->children()->arrayNode('ReplyTo');
        $metadataNode->children()->scalarNode('Email')->isRequired();
        $metadataNode->children()->scalarNode('Name');
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function buildVarsSection(ArrayNodeDefinition $rootNode)
    {
        /** @var ArrayNodeDefinition $mergeNode */
        $mergeNode = $rootNode->children()->arrayNode('Variables')->ignoreExtraKeys();
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function buildAttachmentsSection(ArrayNodeDefinition $rootNode)
    {
        /** @var ArrayNodeDefinition $attachmentsNode */
        $attachmentsNode = $rootNode->children()->arrayNode('Attachments')->prototype('array');
        $attachmentsNode->children()->scalarNode('ContentType')->isRequired();
        $attachmentsNode->children()->scalarNode('Filename')->isRequired();
        $attachmentsNode->children()->scalarNode('Base64Content')->isRequired();
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     */
    protected function buildInlineAttachmentsSection(ArrayNodeDefinition $rootNode)
    {
        /** @var ArrayNodeDefinition $attachmentsNode */
        $attachmentsNode = $rootNode->children()->arrayNode('InlinedAttachments')->prototype('array');//v3.1 new naming
        $attachmentsNode->children()->scalarNode('ContentType')->isRequired();
        $attachmentsNode->children()->scalarNode('Filename')->isRequired();
        $attachmentsNode->children()->scalarNode('ContentID')->isRequired();//v3.1 addition
        $attachmentsNode->children()->scalarNode('Base64Content')->isRequired();
    }
}