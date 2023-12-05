# Carolina University Drupal 9 Custom Upstream

The cu-theme repository serves as a custom upstream project for the following Carolina University websites built on Drupal 9:

1. e4
1. John Wesley School of Leadership
1. Moore School of Education
1. Patterson School of Business
1. Piedmont Divinity School
1. School of Arts & Sciences

## Getting Started for Local Development

All of the above referenced websites

### Prerequisites

- PHP (preferably version 7.3 or higher)
- Composer - Dependency Manager for PHP
- Lando - Local development environment and DevOps tool built on Docker

### Installation

1. **Clone the Repository:**

    For the cu-theme project, use the following as the repository location: `git@github.com:carolinau/cu-theme.git`

    For each of the six websites listed above, log in to Pantheon, select the `dev` environment for the site you're working on and click `Clone with git` to find the repository location.

    ```bash
    git clone [repository location from above]
    cd [install directory]
    ```

    The `install directory` is directory created by the `git clone` command from above.

2. **Install Dependencies:**

    Run the following command to install PHP dependencies using Composer:

    ```bash
    composer install
    ```

3. **Copy Lando Configuration:**

    After installing the dependencies, copy the `.lando/.lando.yml` file to the project root. You can do this manually or by running:

    ```bash
    cp .lando/.lando.yml .
    ```

### Starting the Development Environment

Once you've installed the dependencies and copied the Lando configuration file, you can start the local development environment using Lando:

```bash
lando start
```

This command will initialize the Lando environment based on the configurations specified in .lando/.lando.yml.

Now you're ready to begin working on the Carolina University Drupal 9 project locally! Access your project at the specified local URL provided by Lando.
