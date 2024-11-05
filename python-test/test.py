import requests

try:
    response = requests.get(
        "https://openaipublic.blob.core.windows.net/encodings/cl100k_base.tiktoken"
    )
    response.raise_for_status()  # Will raise an HTTPError for bad responses
    print("Successful request:", response.content)
except requests.exceptions.RequestException as e:
    print("Request failed:", e)
