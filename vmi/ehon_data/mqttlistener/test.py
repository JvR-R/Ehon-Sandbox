import os
import configparser
print("Script started")

def load_configuration():
    # Get the home directory of the current user
    home_dir = os.path.expanduser("~")
    print(f"Home directory: {home_dir}")  # Debug print statement

    # Construct the path to the db_path file
    db_path_file = os.path.join(
        home_dir,
        'public_html',
        'vmi',
        'ehon_data',
        'db',
        'db_path'
    )

    # Check if the db_path file exists
    if not os.path.isfile(db_path_file):
        raise Exception(f"db_path file not found at: {db_path_file}")

    # Read the path to the config file from the db_path file
    with open(db_path_file, 'r') as f:
        config_file_path = f.read().strip()

    # Check if the config file exists
    if not os.path.isfile(config_file_path):
        raise Exception(f"Config file not found at: {config_file_path}")

    # Load the configuration
    config = configparser.ConfigParser()
    config.read(config_file_path)

    return config
# Assuming you have defined the load_configuration() function as before

def main():
    # Call the load_configuration function
    config = load_configuration()
    # You can now use 'config' as needed
    print(config)

if __name__ == "__main__":
    main()
