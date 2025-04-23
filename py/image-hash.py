# image-hash.py
#
# Calculates the correct path for an image inside of the hased subidrectory hierarchy of mediawiki
#
import sys
import hashlib
from pathlib import Path

# Get the input filename from arguments
original = Path(sys.argv[1])

# Compute a hash of the filename string
hash_str = hashlib.md5(original.name.encode('utf-8')).hexdigest()

# Build the path: first/first+second/original_name
output_path = f"{hash_str[0]}/{hash_str[:2]}/{original.name}"

# Print the result
print(output_path)