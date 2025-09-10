import { useRef, useState } from "react";
import { Upload, X, FileText, Image, AlertCircle, CheckCircle2 } from "lucide-react";
import { Progress } from "./ui/progress";
import { Badge } from "./ui/badge";
import { Button } from "./ui/button";
import { Alert, AlertDescription } from "./ui/alert";
import { FilePreview } from "./FilePreview";

interface UploadedFile {
  id: string;
  file: File;
  progress: number;
  status: 'uploading' | 'completed' | 'error';
  preview?: string;
}

interface FileUploadProps {
  onAuthRequired: () => void;
  onShowLogin: () => void;
  isAuthenticated?: boolean;
}

const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
const ALLOWED_TYPES = {
  'application/pdf': 'PDF',
  'application/msword': 'DOC',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'DOCX',
  'image/jpeg': 'JPEG',
  'image/png': 'PNG',
  'image/gif': 'GIF',
  'image/webp': 'WebP'
};

export function FileUpload({ onAuthRequired, onShowLogin, isAuthenticated = false }: FileUploadProps) {
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [isDragOver, setIsDragOver] = useState(false);
  const [uploadedFiles, setUploadedFiles] = useState<UploadedFile[]>([]);
  const [errors, setErrors] = useState<string[]>([]);
  const [selectedFileId, setSelectedFileId] = useState<string | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);

  const validateFile = (file: File): string | null => {
    if (file.size > MAX_FILE_SIZE) {
      return `File "${file.name}" is too large. Maximum size is 10MB.`;
    }
    
    if (!Object.keys(ALLOWED_TYPES).includes(file.type)) {
      return `File "${file.name}" has an unsupported format. Supported formats: PDF, DOC, DOCX, JPEG, PNG, GIF, WebP.`;
    }
    
    return null;
  };

  const createFilePreview = (file: File): Promise<string | undefined> => {
    return new Promise((resolve) => {
      if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target?.result as string);
        reader.readAsDataURL(file);
      } else {
        resolve(undefined);
      }
    });
  };

  const simulateUpload = (fileId: string) => {
    let progress = 0;
    const interval = setInterval(() => {
      progress += Math.random() * 30;
      if (progress >= 100) {
        progress = 100;
        clearInterval(interval);
        setUploadedFiles(prev => 
          prev.map(f => f.id === fileId ? { ...f, progress, status: 'completed' } : f)
        );
      } else {
        setUploadedFiles(prev => 
          prev.map(f => f.id === fileId ? { ...f, progress } : f)
        );
      }
    }, 200 + Math.random() * 300);
  };

  const processFiles = async (files: FileList) => {
    const newErrors: string[] = [];
    const validFiles: File[] = [];

    Array.from(files).forEach(file => {
      const error = validateFile(file);
      if (error) {
        newErrors.push(error);
      } else {
        validFiles.push(file);
      }
    });

    setErrors(newErrors);

    for (const file of validFiles) {
      const preview = await createFilePreview(file);
      const fileId = Math.random().toString(36).substr(2, 9);
      
      const uploadedFile: UploadedFile = {
        id: fileId,
        file,
        progress: 0,
        status: 'uploading',
        preview
      };

      setUploadedFiles(prev => [...prev, uploadedFile]);
      // Select the first uploaded file for preview
      if (!selectedFileId) {
        setSelectedFileId(fileId);
      }
      simulateUpload(fileId);
    }
  };

  // Auth check function
  const checkAuthentication = async (): Promise<boolean> => {
    try {
      const response = await fetch('/api/auth/check', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const data = await response.json();
      return data.authenticated || false;
    } catch (error) {
      console.error('Auth check failed:', error);
      return false;
    }
  };

  // Upload to temporary storage for guest users
  const uploadGuestFiles = async (files: UploadedFile[]) => {
    const formData = new FormData();
    
    files.forEach(uploadedFile => {
      formData.append('files[]', uploadedFile.file);
    });

    try {
      // Get CSRF token
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token } = await tokenResponse.json();

      const response = await fetch('/api/guest/resume-upload', {
        method: 'POST',
        body: formData,
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
      });

      if (!response.ok) {
        throw new Error('Upload failed');
      }

      return await response.json();
    } catch (error) {
      console.error('Guest upload failed:', error);
      throw error;
    }
  };

  // Upload for authenticated users
  const uploadAuthenticatedFiles = async (files: UploadedFile[]) => {
    const formData = new FormData();
    
    files.forEach(uploadedFile => {
      formData.append('files[]', uploadedFile.file);
    });

    try {
      // Get CSRF token
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token } = await tokenResponse.json();

      const response = await fetch('/resume/upload', {
        method: 'POST',
        body: formData,
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
      });

      if (!response.ok) {
        throw new Error('Upload failed');
      }

      return await response.json();
    } catch (error) {
      console.error('Authenticated upload failed:', error);
      throw error;
    }
  };

  const handleFinalUpload = async () => {
    setIsProcessing(true);
    
    try {
      const completedFiles = uploadedFiles.filter(f => f.status === 'completed');
      
      if (completedFiles.length === 0) {
        setErrors(['No files ready for upload']);
        setIsProcessing(false);
        return;
      }

      // Check if user is authenticated
      const isAuthenticated = await checkAuthentication();
      
      if (isAuthenticated) {
        // User is logged in, upload directly
        await uploadAuthenticatedFiles(completedFiles);
        // Handle success - maybe redirect to dashboard or show success message
        console.log('Files uploaded successfully for authenticated user');
      } else {
        // User is not logged in, upload to temporary storage first
        await uploadGuestFiles(completedFiles);
        // Trigger authentication flow immediately
        onAuthRequired();
      }
      
    } catch (error) {
      setErrors(['Upload failed. Please try again.']);
      console.error('Upload process failed:', error);
    } finally {
      setIsProcessing(false);
    }
  };

  // New function to handle upload button click for unauthenticated users
  const handleUploadClick = async () => {
    const completedFiles = uploadedFiles.filter(f => f.status === 'completed');
    
    if (completedFiles.length === 0) {
      setErrors(['No files ready for upload']);
      return;
    }

    // Check if user is authenticated
    const authenticated = await checkAuthentication();
    
    if (!authenticated) {
      // Store files temporarily and redirect to login immediately
      setIsProcessing(true);
      try {
        await uploadGuestFiles(completedFiles);
        // Trigger authentication flow
        onAuthRequired();
      } catch (error) {
        setErrors(['Failed to prepare files for upload. Please try again.']);
        console.error('Guest upload failed:', error);
      } finally {
        setIsProcessing(false);
      }
    } else {
      // If authenticated, proceed with normal upload
      handleFinalUpload();
    }
  };

  const handleFileSelect = () => {
    fileInputRef.current?.click();
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(true);
  };

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
    const files = e.dataTransfer.files;
    if (files.length > 0) {
      processFiles(files);
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (files && files.length > 0) {
      processFiles(files);
    }
  };

  const removeFile = (fileId: string) => {
    setUploadedFiles(prev => {
      const filtered = prev.filter(f => f.id !== fileId);
      // If we're removing the selected file, select the first remaining file
      if (selectedFileId === fileId) {
        setSelectedFileId(filtered.length > 0 ? filtered[0].id : null);
      }
      return filtered;
    });
  };

  const clearErrors = () => {
    setErrors([]);
  };

  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const getFileIcon = (fileType: string) => {
    if (fileType.startsWith('image/')) {
      return <Image className="h-8 w-8 text-primary" />;
    }
    return <FileText className="h-8 w-8 text-secondary" />;
  };

  const selectedFile = uploadedFiles.find(f => f.id === selectedFileId);
  const hasCompletedFiles = uploadedFiles.some(f => f.status === 'completed');

  return (
    <div className="flex-1 p-8 space-y-6">
      {/* Error Messages */}
      {errors.length > 0 && (
        <div className="max-w-6xl mx-auto space-y-2">
          {errors.map((error, index) => (
            <Alert key={index} variant="destructive">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          ))}
          <Button variant="outline" size="sm" onClick={clearErrors} className="btn-secondary-coral hover:btn-secondary-coral">
            Clear Errors
          </Button>
        </div>
      )}

      {/* Main Content Area */}
      {uploadedFiles.length === 0 ? (
        <>
          {/* Upload Area - Show when no files */}
          <div className="flex justify-center">
            <div
              className={`
                w-full max-w-2xl h-80 border-2 border-dashed rounded-xl
                flex flex-col items-center justify-center gap-6
                transition-all duration-300 cursor-pointer bg-card
                ${isDragOver 
                  ? 'border-primary bg-primary/5 scale-[1.02] shadow-lg' 
                  : 'border-border hover:border-primary/50 hover:bg-primary/5 hover:shadow-md'
                }
              `}
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              onDrop={handleDrop}
              onClick={handleFileSelect}
            >
              <div className={`p-4 rounded-full transition-colors ${isDragOver ? 'bg-primary/10' : 'bg-primary/5'}`}>
                <Upload className="h-12 w-12 text-primary" />
              </div>
              <div className="text-center space-y-3">
                <p className="text-foreground">
                  Drag your files to upload or{" "}
                  <button 
                    className="text-primary underline hover:no-underline font-medium transition-colors"
                    onClick={(e) => {
                      e.stopPropagation();
                      handleFileSelect();
                    }}
                  >
                    select files
                  </button>
                </p>
                <p className="text-sm text-muted-foreground">
                  Supports PDF, DOC, DOCX, JPEG, PNG, GIF, WebP (max 10MB)
                </p>
              </div>
            </div>
          </div>

          {/* Login Option - Only show for authenticated users as a logout option */}
          {!isAuthenticated && (
            <div className="flex justify-center">
              <div className="w-full max-w-2xl">
                <div className="text-center">
                  <div className="relative">
                    <div className="absolute inset-0 flex items-center">
                      <div className="w-full border-t border-border"></div>
                    </div>
                    <div className="relative flex justify-center text-sm">
                      <span className="bg-background px-4 text-muted-foreground">or</span>
                    </div>
                  </div>
                  <button 
                    onClick={onShowLogin}
                    className="mt-4 text-primary hover:text-primary/80 transition-colors font-medium"
                  >
                    Already have an account? Login
                  </button>
                </div>
              </div>
            </div>
          )}
        </>
      ) : (
        /* Two-column layout - Show when files are uploaded */
        <div className="max-w-6xl mx-auto">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {/* Left Column - File Preview */}
            <div className="order-2 lg:order-1">
              {selectedFile && (
                <FilePreview 
                  file={selectedFile.file} 
                  preview={selectedFile.preview}
                />
              )}
            </div>

            {/* Right Column - File List and Upload Area */}
            <div className="order-1 lg:order-2 space-y-6">
              {/* Compact Upload Area */}
              <div
                className={`
                  w-full h-32 border-2 border-dashed rounded-xl
                  flex items-center justify-center gap-4
                  transition-all duration-300 cursor-pointer bg-card
                  ${isDragOver 
                    ? 'border-primary bg-primary/5 scale-[1.02] shadow-lg' 
                    : 'border-border hover:border-primary/50 hover:bg-primary/5 hover:shadow-md'
                  }
                `}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
                onClick={handleFileSelect}
              >
                <div className={`p-2 rounded-full transition-colors ${isDragOver ? 'bg-primary/10' : 'bg-primary/5'}`}>
                  <Upload className="h-6 w-6 text-primary" />
                </div>
                <div className="text-center">
                  <p className="text-sm text-foreground">
                    Add more files or{" "}
                    <button 
                      className="text-primary underline hover:no-underline font-medium transition-colors"
                      onClick={(e) => {
                        e.stopPropagation();
                        handleFileSelect();
                      }}
                    >
                      select files
                    </button>
                  </p>
                </div>
              </div>

              {/* Uploaded Files List */}
              <div className="space-y-4">
                <h3 className="font-medium text-card-foreground">Uploaded Files</h3>
                <div className="space-y-3">
                  {uploadedFiles.map((uploadedFile) => (
                    <div
                      key={uploadedFile.id}
                      className={`
                        flex items-center gap-4 p-4 border rounded-xl bg-card shadow-sm hover:shadow-md transition-all cursor-pointer
                        ${selectedFileId === uploadedFile.id ? 'ring-2 ring-primary bg-primary/5' : ''}
                      `}
                      onClick={() => setSelectedFileId(uploadedFile.id)}
                    >
                      {/* File Icon/Preview */}
                      <div className="flex-shrink-0">
                        {uploadedFile.preview ? (
                          <img
                            src={uploadedFile.preview}
                            alt={uploadedFile.file.name}
                            className="h-12 w-12 rounded object-cover"
                          />
                        ) : (
                          getFileIcon(uploadedFile.file.type)
                        )}
                      </div>

                      {/* File Info */}
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                          <p className="truncate">{uploadedFile.file.name}</p>
                          <Badge variant="secondary">
                            {ALLOWED_TYPES[uploadedFile.file.type as keyof typeof ALLOWED_TYPES]}
                          </Badge>
                          {uploadedFile.status === 'completed' && (
                            <CheckCircle2 className="h-4 w-4 text-primary" />
                          )}
                        </div>
                        <p className="text-sm text-muted-foreground mb-2">
                          {formatFileSize(uploadedFile.file.size)}
                        </p>
                        
                        {/* Progress Bar */}
                        {uploadedFile.status === 'uploading' && (
                          <div className="space-y-1">
                            <Progress value={uploadedFile.progress} className="h-2" />
                            <p className="text-xs text-muted-foreground">
                              {Math.round(uploadedFile.progress)}% uploaded
                            </p>
                          </div>
                        )}
                        
                        {uploadedFile.status === 'completed' && (
                          <p className="text-sm text-primary">Upload completed</p>
                        )}
                      </div>

                      {/* Remove Button */}
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e: { stopPropagation: () => void; }) => {
                          e.stopPropagation();
                          removeFile(uploadedFile.id);
                        }}
                        className="flex-shrink-0"
                      >
                        <X className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                </div>
              </div>

              {/* Upload Button - Show when files are completed */}
              {hasCompletedFiles && (
                <div className="pt-4">
                  <Button 
                    onClick={handleUploadClick}
                    disabled={isProcessing}
                    className="w-full h-14 bg-primary hover:bg-primary/90 text-primary-foreground text-lg font-medium shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-[1.02]"
                    size="lg"
                  >
                    {isProcessing ? (
                      <div className="flex items-center space-x-2">
                        <div className="animate-spin rounded-full h-5 w-5 border-2 border-primary-foreground border-t-transparent"></div>
                        <span>Processing...</span>
                      </div>
                    ) : (
                      <div className="flex items-center space-x-2">
                        <Upload className="h-6 w-6" />
                        <span>{isAuthenticated ? 'Upload Resume' : 'Upload Resume & Login'}</span>
                      </div>
                    )}
                  </Button>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
      
      <input
        ref={fileInputRef}
        type="file"
        className="hidden"
        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.webp"
        multiple
        onChange={handleFileChange}
      />
    </div>
  );
}