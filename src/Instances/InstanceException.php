<?php
namespace Instances;

class InstanceException extends \RuntimeException
{
}

final class InstanceConfigurationException extends InstanceException
{
}

final class UnknownInstanceException extends InstanceException
{
}

final class InstanceUnavailableException extends InstanceException
{
}
