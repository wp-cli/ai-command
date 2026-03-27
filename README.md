wp-cli/ai-command
=================

Interacts with the WordPress AI Client

[![Testing](https://github.com/wp-cli/ai-command/actions/workflows/testing.yml/badge.svg)](https://github.com/wp-cli/ai-command/actions/workflows/testing.yml) [![Code Coverage](https://codecov.io/gh/wp-cli/ai-command/branch/main/graph/badge.svg)](https://codecov.io/gh/wp-cli/ai-command/tree/main)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using

This package implements the following commands:

### wp ai

Interacts with the WordPress AI Client for text and image generation.

~~~
wp ai
~~~

**EXAMPLES**

    # Check AI capabilities status
    $ wp ai status
    +------------------+-----------+
    | Capability       | Supported |
    +------------------+-----------+
    | Text Generation  | Yes       |
    | Image Generation | No        |
    +------------------+-----------+

    # Generate text from a prompt
    $ wp ai generate text "Write a haiku about WordPress"
    Success: Generated text:
    Open source and free
    Empowering creators
    WordPress shines bright

    # Generate an image from a prompt
    $ wp ai generate image "A futuristic WordPress logo" --destination-file=logo.png
    Success: Image saved to logo.png

    # Check if a prompt is supported
    $ wp ai check "Summarize this text"
    Success: Text generation is supported for this prompt.



### wp ai check

Checks if a prompt is supported for generation.

~~~
wp ai check <prompt> [--type=<type>]
~~~

**OPTIONS**

	<prompt>
		The prompt to check.

	[--type=<type>]
		Type to check.
		---
		options:
		  - text
		  - image
		---

**EXAMPLES**

    # Check if text generation is supported
    $ wp ai check "Write a poem"

    # Check if image generation is supported
    $ wp ai check "A sunset" --type=image



### wp ai generate

Generates AI content.

~~~
wp ai generate <type> <prompt> [--model=<models>] [--provider=<provider>] [--temperature=<temperature>] [--top-p=<top-p>] [--top-k=<top-k>] [--max-tokens=<tokens>] [--system-instruction=<instruction>] [--destination-file=<file>] [--stdout] [--format=<format>]
~~~

**OPTIONS**

	<type>
		Type of content to generate.
		---
		options:
		  - text
		  - image
		---

	<prompt>
		The prompt to send to the AI.

	[--model=<models>]
		Comma-separated list of models in order of preference. Format: "provider,model" (e.g., "openai,gpt-4" or "openai,gpt-4,anthropic,claude-3").

	[--provider=<provider>]
		Specific AI provider to use (e.g., "openai", "anthropic", "google").

	[--temperature=<temperature>]
		Temperature for generation, typically between 0.0 and 1.0. Lower is more deterministic.

	[--top-p=<top-p>]
		Top-p (nucleus sampling) parameter. Value between 0.0 and 1.0.

	[--top-k=<top-k>]
		Top-k sampling parameter. Positive integer.

	[--max-tokens=<tokens>]
		Maximum number of tokens to generate.

	[--system-instruction=<instruction>]
		System instruction to guide the AI's behavior.

	[--destination-file=<file>]
		For image generation, path to save the generated image.

	[--stdout]
		Output the whole image using standard output (incompatible with --destination-file=)

	[--format=<format>]
		Output format for text.
		---
		default: text
		options:
		  - text
		  - json
		---

**EXAMPLES**

    # Generate text
    $ wp ai generate text "Explain WordPress in one sentence"

    # Generate text with specific settings
    $ wp ai generate text "List 3 WordPress features" --temperature=0.1 --max-tokens=100

    # Generate with top-p and top-k sampling
    $ wp ai generate text "Write a story" --top-p=0.9 --top-k=40

    # Generate with model preferences
    $ wp ai generate text "Write a haiku" --model=openai,gpt-4,anthropic,claude-3

    # Generate with system instruction
    $ wp ai generate text "Explain AI" --system-instruction="Explain as if to a 5-year-old"

    # Generate image
    $ wp ai generate image "A minimalist WordPress logo" --output=wp-logo.png



### wp ai is-supported

Checks whether AI features are supported in the current environment.

~~~
wp ai is-supported 
~~~

Exits with code 0 if AI features are supported, or code 1 if they are not.

**EXAMPLES**

    # Check if AI is supported
    $ wp ai is-supported
    Success: AI features are supported.



### wp ai status

Checks which AI capabilities are currently supported.

~~~
wp ai status [--format=<format>]
~~~

Checks the environment and credentials to determine which AI operations
are available. Displays a table showing supported capabilities.

**OPTIONS**

	[--format=<format>]
		Render output in a particular format.
		---
		default: table
		options:
		  - table
		  - csv
		  - json
		  - yaml
		---

**EXAMPLES**

    # Check AI status
    $ wp ai status
    +------------------+-----------+
    | capability       | supported |
    +------------------+-----------+
    | Text Generation  | Yes       |
    | Image Generation | No        |
    +------------------+-----------+



### wp connectors

Lists and retrieves information about AI connectors.

~~~
wp connectors
~~~

**EXAMPLES**

    # List all available connectors
    $ wp connectors list

    # Get details about a specific connector
    $ wp connectors get openai



### wp connectors get

Gets details about a specific AI connector.

~~~
wp connectors get <connector> [--fields=<fields>] [--format=<format>]
~~~

**OPTIONS**

	<connector>
		The connector ID (e.g., openai, anthropic, google).

	[--fields=<fields>]
		Comma-separated list of fields to include in the output.

	[--format=<format>]
		Render output in a particular format.
		---
		default: table
		options:
		  - table
		  - csv
		  - json
		  - yaml
		---

**EXAMPLES**

    # Get details for the OpenAI connector
    $ wp connectors get openai
    +-----------------+-----------------------------------------------+
    | Field           | Value                                         |
    +-----------------+-----------------------------------------------+
    | name            | OpenAI                                        |
    | description     | Text and image generation with GPT and Dall-E |
    | status          | connected                                     |
    | credentials_url | https://platform.openai.com/api-keys          |
    | api_key         | ••••••••••••6789                              |
    +-----------------+-----------------------------------------------+



### wp connectors list

Lists all available AI connectors.

~~~
wp connectors list [--status=<status>] [--fields=<fields>] [--format=<format>]
~~~

**OPTIONS**

	[--status=<status>]
		Filter connectors by status.
		---
		options:
		  - connected
		  - active
		  - installed
		  - not installed
		---

	[--fields=<fields>]
		Comma-separated list of fields to include in the output.

	[--format=<format>]
		Render output in a particular format.
		---
		default: table
		options:
		  - table
		  - csv
		  - json
		  - yaml
		---

**EXAMPLES**

    # List all connectors
    $ wp connectors list
    +-----------+-----------------------------------------------+---------------+
    | name      | description                                   | status        |
    +-----------+-----------------------------------------------+---------------+
    | Anthropic | Text generation with Claude.                  | not installed |
    | Google    | Text and image generation with Gemini...      | not installed |
    | OpenAI    | Text and image generation with GPT and Dall-E | connected     |
    +-----------+-----------------------------------------------+---------------+

    # List only connected connectors
    $ wp connectors list --status=connected

## Installing

This package is included with WP-CLI itself, no additional installation necessary.

To install the latest version of this package over what's included in WP-CLI, run:

    wp package install git@github.com:wp-cli/ai-command.git

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/wp-cli/ai-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/wp-cli/ai-command/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/wp-cli/ai-command/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

### License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Support

GitHub issues aren't for general support questions, but there are other venues you can try: https://wp-cli.org/#support


*This README.md is generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). To suggest changes, please submit a pull request against the corresponding part of the codebase.*
