import { FileText, Image as ImageIcon } from "lucide-react";
import { Card } from "./ui/card";

interface FilePreviewProps {
  file: File;
  preview?: string;
}

export function FilePreview({ file, preview }: FilePreviewProps) {
  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const getFileTypeDisplay = (mimeType: string) => {
    const typeMap: { [key: string]: string } = {
      'application/pdf': 'PDF',
      'application/msword': 'DOC',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'DOCX',
      'image/jpeg': 'JPEG',
      'image/png': 'PNG',
      'image/gif': 'GIF',
      'image/webp': 'WebP'
    };
    return typeMap[mimeType] || 'Unknown';
  };

  return (
    <Card className="p-6">
      <div className="space-y-4">
        {/* File Header */}
        <div className="border-b border-border pb-4">
          <h3 className="font-semibold text-card-foreground mb-2">File Preview</h3>
          <div className="flex items-center gap-3">
            <div className="p-2 bg-primary/10 rounded-lg">
              {file.type.startsWith('image/') ? (
                <ImageIcon className="h-6 w-6 text-primary" />
              ) : (
                <FileText className="h-6 w-6 text-primary" />
              )}
            </div>
            <div>
              <p className="font-medium text-card-foreground truncate max-w-xs">
                {file.name}
              </p>
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <span>{formatFileSize(file.size)}</span>
                <span>•</span>
                <span>{getFileTypeDisplay(file.type)}</span>
              </div>
            </div>
          </div>
        </div>

        {/* Preview Content */}
        <div className="min-h-[300px] flex items-center justify-center">
          {preview ? (
            // Image preview
            <div className="w-full">
              <img
                src={preview}
                alt={file.name}
                className="max-w-full max-h-[400px] object-contain rounded-lg shadow-sm mx-auto"
              />
            </div>
          ) : file.type === 'application/pdf' ? (
            // PDF placeholder
            <div className="text-center space-y-4">
              <div className="p-6 bg-red-50 rounded-lg">
                <FileText className="h-16 w-16 text-red-600 mx-auto mb-4" />
                <p className="text-red-800 font-medium">PDF Document</p>
                <p className="text-red-600 text-sm">
                  PDF preview not available in browser
                </p>
              </div>
              <p className="text-sm text-muted-foreground">
                Click download to view the full PDF document
              </p>
            </div>
          ) : file.type.includes('document') || file.type.includes('word') ? (
            // Document placeholder
            <div className="text-center space-y-4">
              <div className="p-6 bg-blue-50 rounded-lg">
                <FileText className="h-16 w-16 text-blue-600 mx-auto mb-4" />
                <p className="text-blue-800 font-medium">
                  {getFileTypeDisplay(file.type)} Document
                </p>
                <p className="text-blue-600 text-sm">
                  Document preview not available
                </p>
              </div>
              <p className="text-sm text-muted-foreground">
                Download the file to view its contents
              </p>
            </div>
          ) : (
            // Generic file placeholder
            <div className="text-center space-y-4">
              <div className="p-6 bg-gray-50 rounded-lg">
                <FileText className="h-16 w-16 text-gray-600 mx-auto mb-4" />
                <p className="text-gray-800 font-medium">File Preview</p>
                <p className="text-gray-600 text-sm">
                  Preview not available for this file type
                </p>
              </div>
            </div>
          )}
        </div>

        {/* File Details */}
        <div className="border-t border-border pt-4">
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p className="text-muted-foreground">File Type</p>
              <p className="font-medium text-card-foreground">
                {getFileTypeDisplay(file.type)}
              </p>
            </div>
            <div>
              <p className="text-muted-foreground">File Size</p>
              <p className="font-medium text-card-foreground">
                {formatFileSize(file.size)}
              </p>
            </div>
            <div>
              <p className="text-muted-foreground">Last Modified</p>
              <p className="font-medium text-card-foreground">
                {new Date(file.lastModified).toLocaleDateString()}
              </p>
            </div>
            <div>
              <p className="text-muted-foreground">MIME Type</p>
              <p className="font-medium text-card-foreground text-xs">
                {file.type}
              </p>
            </div>
          </div>
        </div>
      </div>
    </Card>
  );
}